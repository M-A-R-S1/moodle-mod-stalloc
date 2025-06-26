<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal library of functions for module groupdistribution.
 *
 * Contains the algorithm for the group distribution and some helper functions
 * that wrap useful SQL querys.
 *
 * @package    mod_stalloc
 * @subpackage mod_stalloc
 * @copyright  2014 M Schulze, C Usener -> mod_ratingallocate
 * @copyright  based on code by Stefan Koegel copyright (C) 2013 Stefan Koegel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Represents an Edge in the graph to have fixed fields instead of array-fields
 */
defined('MOODLE_INTERNAL') || die();

class edge {
    /** @var from int */
    public $from;
    /** @var to int */
    public $to;
    /** @var weight int Cost for this edge (rating of user) */
    public $weight;
    /** @var space int (places left for choices) */
    public $space;

    public function __construct($from, $to, $weight, $space = 0) {
        $this->from = $from;
        $this->to = $to;
        $this->weight = $weight;
        $this->space = $space;
    }

}

/**
 * Template Class for distribution algorithms
 */
class distributor {

    /** @var $graph Flow-Graph built */
    protected $graph;

    /**
     * Compute the 'satisfaction' functions that is to be maximized by adding the
     * ratings users gave to their allocated choices
     * @param array $ratings
     * @param array $distribution
     * @return integer
     */
    public static function compute_target_function($ratings, $distribution) {
        $functionvalue = 0;
        foreach ($distribution as $chair_id => $chair) {
            // Variable $choice is now an array of userids.
            foreach ($chair as $user_id) {
                // Find the right rating.
                foreach ($ratings as $rating) {
                    if ($rating->user_id == $user_id && $rating->chair_id == $chair_id) {
                        $functionvalue += $rating->rating;
                        continue;
                    }
                }
            }
        }
        return $functionvalue;
    }

    /**
     * Entry-point to call a solver
     * @param int $id current course module id.
     * @param int $course_id current course id.
     * @param object $instance current database record of this course moduls instance.
     */
    public function distribute_users(int $id, int $course_id, object $instance) {
        require_once(__DIR__.'/../config.php');
        global $DB;

        // Load active chairs from this course module.
        $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'active' => 1]);

        // Load all ratings from this course module.
        $rating_data = $DB->get_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id]);

        // Check how many students need to be allocated.
        $user_count = $DB->count_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => -1]);

        // Randomize the order of the ratings to prevent advantages for early entry.
        shuffle($rating_data);

        // Get the total Number of students.
        $student_number = $DB->count_records('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id]);
        $student_number = ceil(($student_number + $student_number*STUDENT_BUFFER));

        $distrubition_key_total_sum = 0;
        foreach ($chair_data as $chair) {
            if($chair->active == 1) {
                $distrubition_key_total_sum += $chair->distribution_key;
            }
        }

        $distributions = $this->compute_distributions($chair_data, $rating_data, $user_count, $student_number, $distrubition_key_total_sum);

        // Perform all allocation manipulation / inserts in one transaction.
        $transaction = $DB->start_delegated_transaction();

        foreach ($distributions as $chair_id => $users) {
            foreach ($users as $user_id) {
                //$DB->insert_record_raw('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $choice_id, 'user_id' => $user_id, 'direct_allocation' => 0, 'checked' => 1]);

                $allocation_data = $DB -> get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $user_id]);
                $updateobject = new stdClass();
                $updateobject->id = $allocation_data->id;
                $updateobject->chair_id = $chair_id;
                $updateobject->direct_allocation = 0;
                $updateobject->thesis_name = "";
                $updateobject->startdate = null;
                $updateobject->checked = 1;
                $DB->update_record_raw('stalloc_allocation', $updateobject);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Extracts a distribution/allocation from the graph.
     *
     * @param $touserid a map mapping from indexes in the graph to userids
     * @param $tochairid a map mapping from indexes in the graph to choiceids
     * @return an array of the form array(groupid => array(userid, ...), ...)
     */
    protected function extract_allocation($touserid, $tochairid) {
        $distribution = array();
        foreach ($tochairid as $index => $groupid) {
            $group = $this->graph[$index];
            $distribution[$groupid] = array();
            foreach ($group as $assignment) {
                $user = intval($assignment->to);
                if (array_key_exists($user, $touserid)) {
                    $distribution[$groupid][] = $touserid[$user];
                }
            }
        }

        return $distribution;
    }

    /**
     * Setup conversions between ids of users and choices to their node-ids in the graph
     * @param type $user_count
     * @param type $rating_data
     * @return array($fromuserid, $touserid, $fromchairid, $tochairid);
     */
    public static function setup_id_conversions($user_count, $rating_data) {
        // These tables convert userids to their index in the graph
        // The range is [1..$usercount].
        $fromuserid = array();
        $touserid = array();
        // These tables convert choiceids to their index in the graph
        // The range is [$usercount + 1 .. $usercount + $choicecount].
        $fromchairid = array();
        $tochairid = array();

        // User counter.
        $ui = 1;
        // Group counter.
        $gi = $user_count + 1;

        // Fill the conversion tables for group and user ids.
        foreach ($rating_data as $rating) {
            if (!array_key_exists($rating->user_id, $fromuserid)) {
                $fromuserid[$rating->user_id] = $ui;
                $touserid[$ui] = $rating->user_id;
                $ui++;
            }
            if (!array_key_exists($rating->chair_id, $fromchairid)) {
                $fromchairid[$rating->chair_id] = $gi;
                $tochairid[$gi] = $rating->chair_id;
                $gi++;
            }
        }

        return array($fromuserid, $touserid, $fromchairid, $tochairid);
    }

    /**
     * Sets up $this->graph
     * @param type $choicecount
     * @param type $user_count
     * @param type $fromuserid
     * @param type $fromchairid
     * @param type $rating_data
     * @param type $chairdata
     * @param type $source
     * @param type $sink
     * @param type $weightmult
     * @param type $student_number
     * @param type $distrubition_key_total_sum
     */
    protected function setup_graph($choicecount, $user_count, $fromuserid, $fromchairid, $rating_data, $chairdata, $source, $sink, $weightmult = 1, $student_number, $distrubition_key_total_sum) {
        require_once(__DIR__.'/../config.php');
        global $DB;
        // Construct the datastructures for the algorithm
        // A directed weighted bipartite graph.
        // A source is connected to all users with unit cost.
        // The users are connected to their choices with cost equal to their rating.
        // The choices are connected to a sink with 0 cost.
        $this->graph = array();
        // Add source, sink and number of nodes to the graph.
        $this->graph[$source] = array();
        $this->graph[$sink] = array();
        $this->graph['count'] = $choicecount + $user_count + 2;

        // Add users and choices to the graph and connect them to the source and sink.
        foreach ($fromuserid as $id => $user) {
            $this->graph[$user] = array();
            $this->graph[$source][] = new edge($source, $user, 0);
        }
        foreach ($fromchairid as $id => $chair) {
            $this->graph[$chair] = array();

            foreach ($chairdata as $chair_data) {
                if($chair_data->id == $id) {
                    // Calculate the maximum number of students which can be allocated for each chair
                    $max_students = ceil(($student_number * $chair_data->distribution_key) / $distrubition_key_total_sum);
                    //$max_allocation_students = floor($max_students * MAX_STUDENT_ALLOCATIONS_PERCENT);
                    $direct_allocated_students = $DB->count_records('stalloc_allocation', ['course_id' => $chair_data->course_id, 'cm_id' => $chair_data->cm_id, 'chair_id' => $chair_data->id, 'checked' => 1, 'direct_allocation' => 1]);
                    $max_allocation_students = $max_students - $direct_allocated_students;

                    $this->graph[$chair][] = new edge($chair, $sink, 0, $max_allocation_students);
                    break;
                }
            }

            //$this->graph[$chair][] = new edge($chair, $sink, 0, $chairdata[$id]->maxsize);

        }

        // Add the edges representing the ratings to the graph.
        foreach ($rating_data as $id => $rating) {
            $user = $fromuserid[$rating->user_id];
            $chair = $fromchairid[$rating->chair_id];
            $weight = $rating->rating;
            if ($weight > 0) {
                $this->graph[$user][] = new edge($user, $chair, $weightmult * $weight);
            }
        }
    }

    /**
     * Augments the flow in the network, i.e. augments the overall 'satisfaction'
     * by distributing users to choices
     * Reverses all edges along $path in $graph
     * @param type $path path from t to s
     * @throws moodle_exception
     */
    protected function augment_flow($path) {
        if (is_null($path) || count($path) < 2) {
            throw new \moodle_exception('invalid_path', 'stalloc');
        }

        // Walk along the path, from s to t.
        for ($i = count($path) - 1; $i > 0; $i--) {
            $from = $path[$i];
            $to = $path[$i - 1];
            $edge = null;
            $foundedgeid = -1;
            // Find the edge.
            foreach ($this->graph[$from] as $index => &$edge) {
                if ($edge->to == $to) {
                    $foundedgeid = $index;
                    break;
                }
            }
            // The second to last node in a path has to be a choice-node.
            // Reduce its space by one, because one user just got distributed into it.
            if ($i == 1 && $edge->space > 1) {
                $edge->space--;
            } else {
                // Remove the edge.
                array_splice($this->graph[$from], $foundedgeid, 1);
                // Add a new edge in the opposite direction whose weight has an opposite sign
                // array_push($this->graph[$to], new edge($to, $from, -1 * $edge->weight));
                // according to php doc, this is faster.
                $this->graph[$to][] = new edge($to, $from, -1 * $edge->weight);
            }
        }
    }

}
