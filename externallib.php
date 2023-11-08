<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");

class local_uniadaptive_external extends external_api {

    //BADGES
    public static function get_course_badges_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED)
            )
        );
    }
    public static function get_course_badges($courseid) {
        global $DB;
        // Get the badges for the course
        $badges = $DB->get_records_sql("SELECT id, name FROM {badge} WHERE courseid = :courseid AND status <> :status", ['courseid' => $courseid, 'status' => 1]);
        $result = array();
        foreach ($badges as $badge) {
            // Get the badge criteria
            $criteria = $DB->get_records('badge_criteria', array('badgeid' => $badge->id), '', 'id, criteriatype, method, descriptionformat');
            $badgecriteria = array();
            foreach ($criteria as $criterion) {
                // Get the badge criteria params
                $params = $DB->get_records('badge_criteria_param', array('critid' => $criterion->id), '', 'id, name, value');
                $criterionparams = array();
                foreach ($params as $param) {
                    $criterionparams[] = array(
                        'id' => $param->id,
                        'name' => $param->name,
                        'value' => $param->value,
                    );
                }
                $badgecriteria[] = array(
                    'id' => $criterion->id,
                    'criteriatype' => $criterion->criteriatype,
                    'method' => $criterion->method,
                    'descriptionformat' => $criterion->descriptionformat,
                    'params' => $criterionparams,
                );
            }
            $result[] = array(
                'id' => $badge->id,
                'name' => $badge->name,
                'params' => $badgecriteria,
            );
        }

        return $result;
    }
    public static function get_course_badges_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Badge ID'),
                    'name' => new external_value(PARAM_TEXT, 'Badge name'),
                    'params' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Criterion ID'),
                                'criteriatype' => new external_value(PARAM_INT, 'Criterion type'),
                                'method' => new external_value(PARAM_INT, 'Criterion method'),
                                'descriptionformat' => new external_value(PARAM_INT, 'Criterion description format'),
                                'params' => new external_multiple_structure(
                                    new external_single_structure(
                                        array(
                                            'id' => new external_value(PARAM_INT, 'Param ID'),
                                            'name' => new external_value(PARAM_TEXT, 'Param name'),
                                            'value' => new external_value(PARAM_TEXT, 'Param value'),
                                        )
                                    )
                                ),
                            )
                        )
                    ),
                )
            )
        );
    }

    //UPDATE BADGES
    public static function update_course_badges_criteria_parameters() {
        return new external_function_parameters(
            array(
                'badges' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Badge ID', VALUE_REQUIRED),
                            'conditions' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'criteriatype' => new external_value(PARAM_INT, 'Criteria Type', VALUE_REQUIRED),
                                        'method' => new external_value(PARAM_INT, 'Method', VALUE_REQUIRED),
                                        'descriptionformat' => new external_value(PARAM_INT, 'Description Format', VALUE_REQUIRED),
                                        'description' => new external_value(PARAM_TEXT, 'Description', VALUE_REQUIRED),
                                        'params' => new external_multiple_structure( 
                                            new external_single_structure(
                                                array(
                                                    'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                                                    'value' => new external_value(PARAM_TEXT, 'Value', VALUE_OPTIONAL),
                                                )
                                            ), 
                                            'Params', 
                                            VALUE_OPTIONAL
                                        ),
                                    ), 
                                    'Conditions', 
                                    VALUE_REQUIRED
                                ),
                            )
                        ),
                        'Badges',
                        VALUE_REQUIRED
                    ),
                )
            )
        );
    }
    public static function update_course_badges_criteria($badges) {
        global $DB;
        if (!is_array($badges)) {
            throw new moodle_exception('Invalid badges data');
        }
        foreach ($badges as $badgeData) {
            if (!isset($badgeData['id']) || !isset($badgeData['conditions'])) {
                throw new moodle_exception('Invalid badge data');
            }
            $id = $badgeData['id'];
            $newcriterias = $badgeData['conditions'];
            // error_log('Longitud: '.count($newcriterias));
            if (!is_array($newcriterias)) {
                throw new moodle_exception('Invalid conditions data');
            }
            $badge = $DB->get_record('badge', array('id' => $id));
            if (!$badge) {
                throw new moodle_exception('Badge not found');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $existing_criteria = $DB->get_records('badge_criteria', array('badgeid' => $badge->id));
                foreach ($existing_criteria as $criterion) {
                    $DB->delete_records('badge_criteria_param', array('critid' => $criterion->id));
                    $DB->delete_records('badge_criteria', array('id' => $criterion->id));
                }
                if(count($newcriterias) > 1){
                    foreach ($newcriterias as $newcriteria) {
                        $criterion = new stdClass();
                        $criterion->badgeid = $badge->id;
                        $criterion->criteriatype = $newcriteria['criteriatype'];
                        $criterion->method = $newcriteria['method'];
                        $criterion->descriptionformat = $newcriteria['descriptionformat'];
                        $criterion->description = $newcriteria['description'];
                        $criteriaid = $DB->insert_record('badge_criteria', $criterion);
                        if (!empty($newcriteria['params'])) {
                            foreach ($newcriteria['params'] as $param) {
                                $paramrecord = new stdClass();
                                $paramrecord->critid = $criteriaid;
                                $paramrecord->name = $param['name'];
                                $paramrecord->value = $param['value'];
                                $id = $DB->insert_record('badge_criteria_param', $paramrecord);
                            }
                        }
                    }
                }
                $transaction->allow_commit();
            } catch (Exception $e) {
                $transaction->rollback($e);
                error_log('ERROR:'. $e->getMessage());
                return array('result' => false);
            }
        }
        return array('result' => true);
    }
    public static function update_course_badges_criteria_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_BOOL, 'Result')
            )
        );
    }

    //Get course id grade
    public static function get_course_grade_id_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }
    public static function get_course_grade_id($courseid){
        $course_grade = self::getCourseGradeId($courseid);
        return array('course_grade_id' => $course_grade);
    }
    private static function getCourseGradeId($courseid){
        global $DB;
        $grade_item = $DB->get_record('grade_items', array(
            'courseid' => $courseid,
            'itemtype' => 'course'
        ), '*', MUST_EXIST);
        return $grade_item->id;
    }
    public static function get_course_grade_id_returns() {
        return new external_single_structure(
            array(
                'course_grade_id' => new external_value(PARAM_INT, 'Course grade ID')
            )
        );
    }
    //GRADES
    public static function get_coursegrades_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }
    public static function get_coursegrades($course_id) {
        $module_grades = self::getCoursegrades($course_id);
        return array('module_grades' => $module_grades);
    }
    // Returns an array with the names of all modules in the course that have grades.
    private static function getCoursegrades($course_id) {
        global $DB;
        $module_grades = $DB->get_records_sql(
            "SELECT gi.itemname
                FROM {grade_items} gi
                JOIN {grade_grades} gg ON gg.itemid = gi.id
                WHERE gi.courseid = :courseid AND gi.itemtype = 'mod' AND gg.rawgrade IS NOT NULL
                GROUP BY gi.itemname",
            array('courseid' => $course_id)
        );
        return array_keys($module_grades);
    }
    public static function get_coursegrades_returns() {
        return new external_single_structure(
            array(
                'module_grades' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Module name')
                )
            )
        );
    }


    //ID GRADES
    public static function get_id_grade_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'cmname' => new external_value(PARAM_TEXT, 'Module name'),
                'cmmodname' => new external_value(PARAM_TEXT, 'Module type'),
                'cminstance' => new external_value(PARAM_INT, 'Instance ID')
            )
        );
    }
    public static function get_id_grade($courseid, $cmname, $cmmodname, $cminstance) {
        // Call the getIdGrade function
        $grade_id = self::getIdGrade($courseid, $cmname, $cmmodname, $cminstance);
        return array('grade_id' => $grade_id);
    }
    public static function get_id_grade_returns() {
        return new external_single_structure(
            array(
                'grade_id' => new external_value(PARAM_INT, 'Grade ID')
            )
        );
    }
    private static function getIdGrade($courseid, $cmname, $cmmodname, $cminstance) {
        global $DB;
        $grade_item = $DB->get_record('grade_items', array(
            'courseid' => $courseid,
            'itemname' => $cmname,
            'itemmodule' => $cmmodname,
            'iteminstance' => $cminstance,
            'itemtype' => 'mod'
        ), '*', MUST_EXIST);
        return $grade_item->id;
    }

    //ID GRADE COURSE
    public static function get_course_grade_with_califications_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }
    public static function get_course_grade_with_califications($courseid) {
        // Call the getCourseGradeWithCalifications function
        $grade_id = self::getCourseGradeWithCalifications($courseid);
        return array('grade_id' => $grade_id);
    }
    private static function getCourseGradeWithCalifications($courseid) {
        global $DB;
        $grade_item = $DB->get_record('grade_items', array(
            'courseid' => $courseid,
            'itemtype' => 'course'
        ));
        if ($grade_item) {
            return $grade_item->id;
        } else {
            return null;
        }
    }
    public static function get_course_grade_with_califications_returns() {
        return new external_single_structure(
            array(
                'grade_id' => new external_value(PARAM_INT, 'Grade item ID', VALUE_OPTIONAL)
            )
        );
    }
    
    //EXPORT MODULES
    public static function set_modules_list_by_sections_parameters() {
        return new external_function_parameters(
            array(
                'sections' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Section ID'),
                            'sequence' => new external_multiple_structure(
                                new external_value(PARAM_INT, 'Module ID')
                            )
                        )
                    )
                ),
                'modules' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Module ID'),
                            'section' => new external_value(PARAM_INT, 'Section ID'),
                            'indent' => new external_value(PARAM_INT, 'Indentation level'),
                            'c' => new external_value(PARAM_RAW, 'Availability conditions', VALUE_OPTIONAL),
                            'lmsVisibility' => new external_value(PARAM_BOOL, 'Visibility'),
                            'order' => new external_value(PARAM_INT, 'Order', VALUE_OPTIONAL)
                        )
                    )
                )
            )
        );
    }
    public static function set_modules_list_by_sections($sections, $modules) {
        // Call the setModulesListBySections function
        $result = self::setModulesListBySections($sections, $modules);
        return array('result' => $result);
    }
    private static function setModulesListBySections($sections, $modules) {
        global $DB;
        try {
            $transaction = $DB->start_delegated_transaction();

            foreach ($sections as $section) {
                $DB->set_field('course_sections', 'sequence', implode(',', $section['sequence']), array('id' => $section['id']));
            }
            foreach ($modules as $module) {
                $conditions = null;
                if (isset($module['c'])) {
                    $conditions = $module['c'];
                }
                $DB->update_record('course_modules', (object)array(
                    'id' => $module['id'],
                    'section' => $module['section'],
                    'indent' => $module['indent'],
                    'availability' => $conditions,
                    'visible' => $module['lmsVisibility']
                ));
            }
            $transaction->allow_commit();
            purge_all_caches();
            return true;
        } catch (\Exception $e) {
            // An error occurred, the changes will be reverted automatically.
            error_log($e);
            return false;
        }
    }
    public static function set_modules_list_by_sections_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_BOOL, 'Result')
            )
        );
    }

    //GET MODULES
    public static function get_course_modules_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'exclude' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Module type name to exclude'),
                    'List of module type names to exclude',
                    VALUE_DEFAULT,
                    array()
                ),
                'invert' => new external_value(PARAM_BOOL, 'Invert search', VALUE_DEFAULT, false)
            )
        );
    }
    public static function get_course_modules($courseid, $exclude = array(), $invert = false) {
        // Call the getCourseModules function
        $modules = self::getCourseModules($courseid, $exclude, $invert);
        return array('modules' => $modules);
    }
    private static function getCourseModules($courseid, $exclude, $invert) {
        global $DB;
        $modulesList = $DB->get_records('course_modules', array('course' => $courseid, 'deletioninprogress' => !$exclude), '', 'id, module, instance, section');
        $modules = array();
        foreach ($modulesList as $cm) {
            $modname = $DB->get_field('modules', 'name', array('id' => $cm->module));
            if ($invert) {
                if (in_array($modname, $exclude)) {
                    $module_data =  $DB->get_record($modname, array('id' => $cm->instance), 'name');
                    $section = $DB->get_record('course_sections', array('id' => $cm->section), 'section');
                    array_push($modules, [
                        'id' => $cm->id,
                        'modname' => $modname,
                        'name' => $module_data->name,
                        'section' => $section->section
                    ]);
                }
            } else {
                if (!in_array($modname, $exclude)) {
                    $module_data =  $DB->get_record($modname, array('id' => $cm->instance), 'name');
                    $section = $DB->get_record('course_sections', array('id' => $cm->section), 'section');
                    array_push($modules, [
                        'id' => $cm->id,
                        'modname' => $modname,
                        'name' => $module_data->name,
                        'section' => $section->section
                    ]);
                }
            }
        }
        return $modules;
    }
    public static function get_course_modules_returns() {
        return new external_single_structure(
            array(
                'modules' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Module ID'),
                            'modname' => new external_value(PARAM_TEXT, 'Module type name'),
                            'name' => new external_value(PARAM_TEXT, 'Module name'),
                            'section' => new external_value(PARAM_INT, 'Section ID')
                        )
                    )
                )
            )
        );
    }
    

    //GET MODULES BY TYPE
    public static function get_course_modules_by_type_parameters(){
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'type' => new external_value(PARAM_TEXT, 'Type name'),
                'excludestatusdelete' => new external_value(PARAM_BOOL, 'Exclude the modules that have delete status', VALUE_DEFAULT, false)
            )
        );
    }
    public static function get_course_modules_by_type($courseid, $type, $exclude){
        global $DB;
        $modules = [];
        $module_name = $DB->get_record('modules', array('name' => $type), 'id, name');
        $modules_course = $DB->get_records('course_modules', array('course' => $courseid, 'module' => $module_name->id, 'deletioninprogress' => (int) !$exclude), '', 'id, instance, section');
        foreach ($modules_course as $module) {
            $module_item = $DB->get_record($type, array('id' =>  $module->instance, 'course' => $courseid), 'name');
            $section = $DB->get_record('course_sections', array('id' =>  $module->section), 'section');
            array_push($modules, [
                'id' => $module->id,
                'name' => $module_item->name,
                'section' => $section->section
            ]);
        }
        return ['modules' => $modules];
    }
    public static function get_course_modules_by_type_returns() {
        return new external_single_structure(
            array(
                'modules' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Module ID'),
                            'name' => new external_value(PARAM_TEXT, 'Module name'),
                            'section' => new external_value(PARAM_INT, 'Section module'),
                        )
                    )
                )
            )
        );
    }
    
    //GET MODULES LIST BY SECTIONS COURSE
    public static function get_modules_list_by_sections_course_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }
    public static function get_modules_list_by_sections_course($courseid) {
        global $DB;
        $sections = $DB->get_records('course_sections', array('course' => $courseid), '', 'id, sequence');
        foreach ($sections as $section) {
            $array = explode(",", $section->sequence);
            // error_log(json_encode($array));
            $section->sequence = array_map('intval', $array);
            foreach ($section->sequence as $key =>$sequence) {
                if($sequence == 0){
                    // array_push($sections, $sequence);
                    unset($section->sequence[$key]);
                }
            }
        }
    
        return array('sections' => array_values($sections));
    }
    public static function get_modules_list_by_sections_course_returns() {
        return new external_single_structure(
            array(
                'sections' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Section ID'),
                            'sequence' => new external_multiple_structure(
                                new external_value(PARAM_RAW, 'Module ID', VALUE_OPTIONAL)
                            )
                        )
                    )
                )
            )
        );
    }

    //ITEM FOR GRADE ID
    public static function get_course_item_id_for_grade_id_parameters() {
        return new external_function_parameters(
            array(
                'gradeid' => new external_value(PARAM_INT, 'Grade item ID')
            )
        );
    }
    public static function get_course_item_id_for_grade_id($gradeid) {
        global $DB;
        // Get the grade item record
        $grade_item = $DB->get_record('grade_items', array(
            'id' => $gradeid
        ));
        if($grade_item->itemtype !== "course"){
            // Get the module record for the course module
            $module = $DB->get_record('modules', array(
                'name' => $grade_item->itemmodule
            ));
            // Get the course module record for the grade item
            $course_module = $DB->get_record('course_modules', array(
                'instance' => $grade_item->iteminstance,
                'module' => $module->id
            ));
            return array('itemid' => $course_module->id, 'itemtype' => $grade_item->itemtype);
        } else {
            return array('itemid' => $grade_item->courseid, 'itemtype' => $grade_item->itemtype);
        }
    }
    public static function get_course_item_id_for_grade_id_returns() {
        return new external_single_structure(
            array(
                'itemid' => new external_value(PARAM_INT, 'Course module ID', VALUE_OPTIONAL),
                'itemtype' => new external_value(PARAM_TEXT, 'Course type', VALUE_OPTIONAL)
            )
        );
    }

    //GET ROLES
    public static function get_assignable_roles_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'Context ID')
            )
        );
    }
    public static function get_assignable_roles() {
        global $DB;
        $roles = $DB->get_records('role');
        $result = array();
        foreach ($roles as $role) {
            // Get the capabilities of the role in the system context
            $context = context_system::instance();
            $capabilities = role_context_capabilities($role->id, $context);
            // Check if the role has the 'moodle/badges:awardbadge' capability
            if (isset($capabilities['moodle/badges:awardbadge']) && $capabilities['moodle/badges:awardbadge']) {
                $result[] = array(
                    'id' => $role->id,
                    'name' => role_get_name($role, $context),
                );
            }
        }
        return $result;
    }
    public static function get_assignable_roles_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Role ID'),
                    'name' => new external_value(PARAM_TEXT, 'Role name'),
                )
            )
        );
    }

    //COMPETENCIES
    public static function get_course_competencies_parameters() {
        return new external_function_parameters(
            array(
                'idnumber' => new external_value(PARAM_TEXT, 'Course ID number')
            )
        );
    }
    
    public static function get_course_competencies($courseid) {
        global $DB;
        // Get the competencies for the course
        $course_comps = $DB->get_records('competency_coursecomp', array('courseid' => $courseid), '', 'id, competencyid');
        $result = [];
        foreach ($course_comps as $competency) {
            $comps_data = $DB->get_records('competency', array('id' => $competency->competencyid),'', 'id, shortname');
            foreach ($comps_data as $comp_data) {
                array_push($result,[
                    'id' => $comp_data->id,
                    'name' => $comp_data->shortname
                ]);
            }
            
        }
        return $result;
    }
    
    public static function get_course_competencies_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Competency ID'),
                    'name' => new external_value(PARAM_TEXT, 'Competency name'),
                )
            )
        );
    }
   
    //UPDATE COURSE
    public static function update_course_parameters() {   
        return new external_function_parameters(
            array(
                'data' => new external_single_structure(
                    array(
                        'sections' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'Section ID', VALUE_OPTIONAL),
                                    'sequence' => new external_multiple_structure(
                                        new external_value(PARAM_RAW, 'Module ID sequence', VALUE_OPTIONAL),
                                         'sequence',
                                          VALUE_OPTIONAL
                                    )
                                )
                            ),
                            "sections",
                            VALUE_OPTIONAL
                        ),
                        'modules' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'Module ID', VALUE_REQUIRED),
                                    'section' => new external_value(PARAM_INT, 'Section ID', VALUE_REQUIRED),
                                    'indent' => new external_value(PARAM_INT, 'Indentation level', VALUE_REQUIRED),
                                    'g' => new external_single_structure(
                                        array(
                                            'hasConditions' => new external_value(PARAM_BOOL, 'Has conditions', VALUE_REQUIRED),
                                            'hasToBeSeen' => new external_value(PARAM_BOOL, 'Has to be seen', VALUE_REQUIRED),
                                            'hasToBeQualified' => new external_value(PARAM_BOOL, 'Calification required', VALUE_REQUIRED),
                                            'data' => new external_single_structure(
                                                array(
                                                    'min' => new external_value(PARAM_TEXT, 'Minimal calification', VALUE_REQUIRED),
                                                    'max' => new external_value(PARAM_TEXT, 'Maximal calification', VALUE_REQUIRED),
                                                    'hasToSelect' => new external_value(PARAM_BOOL, 'Has to select', VALUE_REQUIRED)
                                                )
                                            )
                                        ),
                                        'Calification conditions',
                                        VALUE_OPTIONAL
                                    ),
                                    'c' => new external_value(PARAM_RAW, 'Availability conditions', VALUE_OPTIONAL),
                                    'lmsVisibility' => new external_value(PARAM_BOOL, 'Visibility', VALUE_REQUIRED),
                                    'order' => new external_value(PARAM_INT, 'Order', VALUE_OPTIONAL)
                                )
                            ),
                            "Modules",
                            VALUE_OPTIONAL
                        ),
                        'badges' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'Badge ID', VALUE_OPTIONAL),
                                    'conditions' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'criteriatype' => new external_value(PARAM_INT, 'Criteria Type', VALUE_OPTIONAL),
                                                'method' => new external_value(PARAM_INT, 'Method', VALUE_OPTIONAL),
                                                'descriptionformat' => new external_value(PARAM_INT, 'Description Format', VALUE_OPTIONAL),
                                                'description' => new external_value(PARAM_TEXT, 'Description', VALUE_OPTIONAL),
                                                'params' => new external_multiple_structure( 
                                                    new external_single_structure(
                                                        array(
                                                            'name' => new external_value(PARAM_TEXT, 'Name', VALUE_OPTIONAL),
                                                            'value' => new external_value(PARAM_TEXT, 'Value', VALUE_OPTIONAL),
                                                        )
                                                    ), 
                                                    'Params', 
                                                    VALUE_OPTIONAL
                                                ),
                                            ), 
                                        ),
                                        'Conditions', 
                                        VALUE_OPTIONAL
                                    ),
                                ),
                            ),
                            'Badges',
                            VALUE_OPTIONAL
                        )                
                    )
                )
            )
        );
    }
    public static function update_course($data) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $sections = $data['sections'];
        $modules = $data['modules'];
        $badges = $data['badges'];
        try {
            $transaction = $DB->start_delegated_transaction();
            // Update modules and sections
            if($sections !== null && is_array($sections) && count($sections) > 0){
                foreach ($sections as $section) {
                    if(isset($section['sequence'])){
                        if(count( $section['sequence']) > 1){
                            $sequence = implode(',', $section['sequence']);
                        }else{
                            $sequence =  $section['sequence'][0];
                        }
                        $DB->set_field('course_sections', 'sequence', $sequence , array('id' => $section['id']));
                    }else{
                        $DB->set_field('course_sections', 'sequence', '' , array('id' => $section['id']));
                    }
                }
            }

            if($modules !== null && is_array($modules) && count($modules) > 0){
                $course_id = $DB->get_record('course_modules', array('id' => $modules[0]['id']), 'course');
                $grade_items = $DB->get_records('grade_items', array('courseid' => $course_id->course, 'itemtype' => 'mod'));
                foreach ($modules as $module) {
                    $conditions = null;
                    if (isset($module['c'])) {
                        $conditions = $module['c'];
                    }
                    $completion = 0;
                    if(isset($module['g'])){
                        $course_module = $DB->get_record('course_modules', array('id' => $module['id']), 'id, course, module, instance');
                        $module_record = $DB->get_record('modules', array('id' => $course_module->module));
                        $grade_item = $DB->get_record('grade_items', array('iteminstance' => $course_module->instance, 'itemmodule' => $module_record->name));
                        $gradepass = (float) $module['g']['data']['min'];
                        $grademax = (float) $module['g']['data']['max'];
                        switch ($module_record->name) {
                            case 'resource':
                            case 'folder':
                            case 'label':
                            case 'generic':
                            case 'book':
                            case 'page':
                            case 'url':
                                if($module['g']['hasToBeSeen']){
                                    $completion++;
                                }
                                break;
                            default:
                                if($module['g']['hasConditions']){
                                    $completion++;
                                    if($module['g']['hasToBeSeen'] || $module['g']['hasToBeQualified'])
                                    $completion++;
                                }
                                break;
                        }
                        if($grade_item){
                            switch ($module_record->name) {
                                case 'quiz':
                                    $DB->update_record('grade_items', (object)array(
                                        'id' => $grade_item->id,
                                        'gradepass' => $gradepass < 1 ? 100: $gradepass,
                                        'gradetype' => $module['g']['hasConditions'] && $module['g']['hasToBeQualified'] ? 1 : 3
                                    ));
                                    break;
                                case 'glossary':
                                case 'forum':
                                    $DB->update_record('grade_items', (object)array(
                                        'id' => $grade_item->id,
                                        'gradepass' => $gradepass,
                                        'grademax' => $grademax < 1 ? 100: $grademax,
                                        'gradetype' => $module['g']['hasConditions'] && $module['g']['hasToBeQualified'] ? 1 : 3
                                    ));
                                    $DB->update_record($module_record->name, (object)array(
                                        'id' => $course_module->instance,
                                        'assessed' => $module['g']['hasToBeQualified'] ? 3 : 0
                                    ));
                                    break;
                                default:
                                    $DB->update_record('grade_items', (object)array(
                                        'id' => $grade_item->id,
                                        'gradepass' => $gradepass < 1 ? 0: $gradepass,
                                        'grademax' => $grademax < 1 ? 100: $grademax,
                                        'gradetype' => $module['g']['hasConditions'] && $module['g']['hasToBeQualified'] ? 1 : 3
                                        //AQUI
                                    ));
                                    break;
                            }
                        }else{
                            switch ($module_record->name) {
                                case 'resource':
                                case 'folder':
                                case 'label':
                                case 'generic':
                                case 'book':
                                case 'page':
                                case 'url':
                                    break;
                                default:
                                    // error_log('ENTRO');
                                    $data_item = $DB->get_record($module_record->name, array('id' => $course_module->instance, 'course' => $course_module->course));
                                    $DB->update_record($module_record->name, (object)array(
                                        'id' => $course_module->instance,
                                        'assessed' => $module['g']['hasToBeQualified'] ? 3 : 0
                                    ));
                                    $max_sortorder = 0;
                                    $category_id = null;
                                    foreach ($grade_items as $grade_item) {
                                        if ($grade_item->sortorder > $max_sortorder) {
                                            $max_sortorder = $grade_item->sortorder;
                                        }
                                        if ($grade_item->categoryid != null){
                                            $category_id = $grade_item->categoryid;
                                        }
                                        
                                    }
                                    $new_grade_item = new stdClass();
                                    $new_grade_item->courseid = $course_id->course;
                                    $new_grade_item->categoryid = $category_id;
                                    $new_grade_item->itemname = $data_item->name;
                                    $new_grade_item->itemtype = 'mod';
                                    $new_grade_item->itemmodule = $module_record->name;
                                    $new_grade_item->itemnumber = 0;
                                    $new_grade_item->idnumber = '';
                                    $new_grade_item->iteminstance = $course_module->instance;
                                    $new_grade_item->gradetype = $module['g']['hasConditions'] ? 1 : 3;
                                    $new_grade_item->gradepass = $gradepass;
                                    $new_grade_item->grademax = $grademax < 1 ? 100 : $grademax;
                                    $new_grade_item->sortorder = $max_sortorder;
                                    $new_grade_item->timecreated = time();
                                    $new_grade_item->timemodified = time();
                                    $new_grade_item->id = $DB->insert_record('grade_items', $new_grade_item);
                                    break;
                            }
                        }
                    }
                    $DB->update_record('course_modules', (object)array(
                        'id' => $module['id'],
                        'section' => $module['section'],
                        'indent' => $module['indent'],
                        'availability' => $conditions,
                        'visible' => $module['lmsVisibility'],
                        'completion' => $completion,
                        'completionview' => (int) $module['g']['hasToBeSeen'],
                        'completiongradeitemnumber' => $module['g']['hasToBeQualified'] ? 0 : null
                    ));
                }
                $grade_items = $DB->get_records('grade_items', array('courseid' => $course_id->course, 'itemtype' => 'mod', 'gradetype' => 1));
                $sum_grademax = 0;
                foreach ($grade_items as $grade_item) {
                    $sum_grademax += $grade_item->grademax;
                }
                $course_grade_item = $DB->get_record('grade_items', array('courseid' => $course_id->course, 'itemtype' => 'course'));
                $DB->update_record('grade_items', (object)array(
                    'id' => $course_grade_item->id,
                    'grademax' => $sum_grademax
                ));
                foreach ($grade_items as $grade_item) {
                    $DB->update_record('grade_items', (object)array(
                        'id' => $grade_item->id,
                        'aggregationcoef2' => $grade_item->grademax / $sum_grademax,
                        'weightoverride' => 0
                    ));
                }
            }
            // Update badges
            if ($badges !== null && is_array($badges) && count($badges) > 0) {
                foreach ($badges as $badgeData) {
                    if (!isset($badgeData['id'])) {
                        // throw new moodle_exception('Invalid badge data');
                        return array('status' => false, 'error' => 'INVALID_BADGE_DATA');
                    }
                    $id = $badgeData['id'];
                    $newcriterias = $badgeData['conditions'];
                    $badge = $DB->get_record('badge', array('id' => $id));
                    if (!$badge) {
                        // throw new moodle_exception('Badge not found');
                        return array('status' => false, 'error' => 'BADGE_NOT_FOUND');
                    }
                    // Delete existing criteria
                    $existing_criteria = $DB->get_records('badge_criteria', array('badgeid' => $badge->id));
                    foreach ($existing_criteria as $criterion) {
                        $DB->delete_records('badge_criteria_param', array('critid' => $criterion->id));
                        $DB->delete_records('badge_criteria', array('id' => $criterion->id));
                    }
                    // Insert new criteria
                    if (is_array($newcriterias) && (count($newcriterias) >= 1)) {
                        foreach ($newcriterias as $newcriteria) {
                            $criterion = new stdClass();
                            $criterion->badgeid = $badge->id;
                            $criterion->criteriatype = $newcriteria['criteriatype'];
                            $criterion->method = $newcriteria['method'];
                            $criterion->descriptionformat = $newcriteria['descriptionformat'];
                            $criterion->description = $newcriteria['description'];
                            $criteriaid = $DB->insert_record('badge_criteria', $criterion);
                            if (!empty($newcriteria['params'])) {
                                foreach ($newcriteria['params'] as $param) {
                                    $paramrecord = new stdClass();
                                    $paramrecord->critid = $criteriaid;
                                    $paramrecord->name = $param['name'];
                                    $paramrecord->value = $param['value'];
                                    $id = $DB->insert_record('badge_criteria_param', $paramrecord);
                                }
                            }
                        }
                    }
                }
            }
            // Commit transaction
            $transaction->allow_commit();
            purge_all_caches();
            return array('status' => true, 'error' => '');
        } catch (\Exception $e) {
            // An error occurred, the changes will be reverted automatically.
            error_log($e);
            return array('status' => false, 'error' => 'ERROR_OCURRED');
        }
    }
    public static function update_course_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'Status'),
                'error' => new external_value(PARAM_TEXT, 'Error message')
            )
        );
    }
    // Get califications
    public static function get_module_data_parameters() {
        return new external_function_parameters(
            array(
                'moduleid' => new external_value(PARAM_INT, 'Module ID', VALUE_REQUIRED),
                'itemmodule' => new external_value(PARAM_TEXT, 'Item Module', VALUE_REQUIRED)
            )
        );
    }
    public static function get_module_data($moduleid, $itemmodule) {
        global $DB;
        // Get the data from mdl_course_modules
        $course_module = $DB->get_record('course_modules', array('id' => $moduleid), 'completion, completionview, completiongradeitemnumber, instance');
        // Get the instance ID of the module
        $module_instance_id = $course_module->instance;
        // Get the data from mdl_grade_items
        $grade_item = $DB->get_record('grade_items', array('iteminstance' => $module_instance_id, 'itemmodule' => $itemmodule), 'gradetype, grademax, gradepass, scaleid');
        $module_data = '';
        // Depending on the type of module, get the corresponding data
        switch ($itemmodule) {
            case 'quiz':
                $module_data = $DB->get_record('quiz', array('id' => $module_instance_id), 'completionpass, completionattemptsexhausted');
                if ($module_data != '') {
        
                    $result = [
                        'hasConditions' => $course_module->completion > 0 ? true : false,
                        'hasToBeSeen' => (bool) $course_module->completionview,
                        'hasToBeQualified' => $course_module->completiongradeitemnumber == null || $course_module->completiongradeitemnumber == 1 ? false : true,
                        'data' => [
                            'min' => $grade_item->gradepass == null ? "0.00000": $grade_item->gradepass,
                            'max' => $grade_item->grademax == null ? "10.00000": $grade_item->grademax,
                            'hasToSelect' => false
                        ],
                    ];
                }
                // error_log(json_encode($result));
                return array('status' => true, 'error' => '', 'data'=> $result);
                break;
            case 'assign':
                $module_data = $DB->get_record('assign', array('id' => $module_instance_id), 'completionsubmit');
                if ($module_data != '') {
        
                    $result = [
                        'hasConditions' => $course_module->completion > 0 ? true : false,
                        'hasToBeSeen' => (bool) $course_module->completionview,
                        'hasToBeQualified' => $course_module->completiongradeitemnumber == null || $course_module->completiongradeitemnumber == 1 ? false : true,
                        'data' => [
                            'min' => $grade_item->gradepass == null ? "0.00000": $grade_item->gradepass,
                            'max' => $grade_item->grademax == null ? "100.00000": $grade_item->grademax,
                            'hasToSelect' => false
                        ],
                    ];
                }
                // error_log(json_encode($result));
                return array('status' => true, 'error' => '', 'data'=> $result);
                break;
            case 'forum':
                $module_data = $DB->get_record('forum', array('id' => $module_instance_id), 'assessed, scale');
                if ($module_data != '') {
        
                    $result = [
                        'hasConditions' => $course_module->completion > 0 ? true : false,
                        'hasToBeSeen' => (bool) $course_module->completionview,
                        'hasToBeQualified' => $course_module->completiongradeitemnumber == null || $course_module->completiongradeitemnumber == 1 ? false : true,
                        'data' => [
                            'min' => $grade_item->gradepass == null ? "0.00000": $grade_item->gradepass,
                            'max' => $grade_item->grademax == null ? "100.00000": $grade_item->grademax,
                            'hasToSelect' => false
                        ],
                    ];
                }
                // error_log(json_encode($result));
                return array('status' => true, 'error' => '', 'data'=> $result);
                break;
            case 'workshop':
                $module_data = $DB->get_record('workshop', array('id' => $module_instance_id), 'strategy');
                if ($module_data != '') {
        
                    $result = [
                        'hasConditions' => $course_module->completion > 0 ? true : false,
                        'hasToBeSeen' => (bool) $course_module->completionview,
                        'hasToBeQualified' => $course_module->completiongradeitemnumber == null || $course_module->completiongradeitemnumber == 1 ? false : true,
                        'data' => [
                            'min' => $grade_item->gradepass == null ? "0.00000": $grade_item->gradepass,
                            'max' => $grade_item->grademax == null ? "100.00000": $grade_item->grademax,
                            'hasToSelect' => false
                        ],
                    ];
                }
                // error_log(json_encode($result));
                return array('status' => true, 'error' => '', 'data'=> $result);
                break;
            case 'choice':
                $module_data = $DB->get_record('choice', array('id' => $module_instance_id), 'completionsubmit');
                if ($module_data != '') {
        
                    $result = [
                        'hasConditions' => $course_module->completion > 0 ? true : false,
                        'hasToBeSeen' => (bool) $course_module->completionview,
                        'hasToBeQualified' => $course_module->completiongradeitemnumber == null || $course_module->completiongradeitemnumber == 1 ? false : true,
                        'data' => [
                            'min' => $grade_item->gradepass == null ? "0.00000": $grade_item->gradepass,
                            'max' => $grade_item->grademax == null ? "100.00000": $grade_item->grademax,
                            'hasToSelect' => (bool) $module_data->completionsubmit
                        ],
                    ];
                }
                // error_log(json_encode($result));
                return array('status' => true, 'error' => '', 'data'=> $result);
                break;
            case 'glossary':
                $module_data = $DB->get_record('glossary', array('id' => $module_instance_id), 'assessed, scale');
                if ($module_data != '') {
        
                    $result = [
                        'hasConditions' => $course_module->completion > 0 ? true : false,
                        'hasToBeSeen' => (bool) $course_module->completionview,
                        'hasToBeQualified' => $course_module->completiongradeitemnumber == null || $course_module->completiongradeitemnumber == 1 ? false : true,
                        'data' => [
                            'min' => $grade_item->gradepass == null ? "0.00000": $grade_item->gradepass,
                            'max' => $grade_item->grademax == null ? "100.00000": $grade_item->grademax,
                            'hasToSelect' => false
                        ],
                    ];
                }
                // error_log(json_encode($result));
                return array('status' => true, 'error' => '', 'data'=> $result);
                break;
            // Add here more cases for other module types
            default:
                $result = [
                    'hasConditions' => $course_module->completion > 0 ? true : false,
                    'hasToBeSeen' => (bool) $course_module->completionview,
                    'hasToBeQualified' => false,
                    'data' => [
                        'min' => "0.00000",
                        'max' => "0.00000",
                        'hasToSelect' => false
                    ],
                ];
                return array('status' => true, 'error' => 'NOT_SUPPORTED', 'data'=> $result);
                break;
                // throw new Exception("Tipo de módulo no soportado: " . $itemmodule);
        }
    }
    public static function get_module_data_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'Status'),
                'error' => new external_value(PARAM_TEXT, 'Error message'),
                'data' => new external_single_structure(
                    array(
                        'hasConditions' => new external_value(PARAM_BOOL, 'Competency ID'),
                        'hasToBeSeen' => new external_value(PARAM_BOOL, 'Competency name'),
                        'hasToBeQualified' => new external_value(PARAM_BOOL, 'Competency name'),
                        'data' => new external_single_structure(
                            array(
                                'min' => new external_value(PARAM_RAW, 'Competency ID'),
                                'max' => new external_value(PARAM_RAW, 'Competency name'),
                                'hasToSelect' => new external_value(PARAM_BOOL, 'Competency name'),
                            )
                        )
                    ),
                    'data',
                    VALUE_OPTIONAL
                )
            )
        );
    }
}
