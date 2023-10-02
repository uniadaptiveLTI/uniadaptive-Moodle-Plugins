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
        $badges = $DB->get_records('badge', array('courseid' => $courseid), '', 'id, name');
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
    

    //GRADES
    public static function get_coursegrades_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }

    public static function get_coursegrades($course_id) {
        global $DB;

        // Check if the user has the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        // Call the getCoursegrades function
        $module_grades = self::getCoursegrades($course_id);

        return array('module_grades' => $module_grades);
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
        global $DB;

        // Check if the user has the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

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
        global $DB;
    
        // Check if the user with the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:view', $context);
    
        // Call the getCourseGradeWithCalifications function
        $grade_id = self::getCourseGradeWithCalifications($courseid);
    
        return array('grade_id' => $grade_id);
    }
    
    public static function get_course_grade_with_califications_returns() {
        return new external_single_structure(
            array(
                'grade_id' => new external_value(PARAM_INT, 'Grade item ID', VALUE_OPTIONAL)
            )
        );
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
        global $DB;
    
        // Check if the user has the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:view', $context);
    
        // Call the getCourseModules function
        $modules = self::getCourseModules($courseid, $exclude, $invert);
    
        return array('modules' => $modules);
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
    
    private static function getCourseModules($courseid, $exclude, $invert) {
        global $DB;
    
        $modules = array();
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            if ($invert) {
                if (in_array($cm->modname, $exclude)) {
                    $module = array(
                        'id' => $cm->id,
                        'modname' => $cm->modname,
                        'name' => $cm->name,
                        'section' => $cm->section
                    );
                    array_push($modules, $module);
                }
            } else {
                if (!in_array($cm->modname, $exclude)) {
                    $module = array(
                        'id' => $cm->id,
                        'modname' => $cm->modname,
                        'name' => $cm->name,
                        'section' => $cm->section
                    );
                    array_push($modules, $module);
                }
            }
        }
    
        return $modules;
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

        // Check if the user has the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        $sections = $DB->get_records('course_sections', array('course' => $courseid), '', 'id, sequence');
        foreach ($sections as $section) {
            $array = explode(",", $section->sequence);
            $section->sequence = array_map('intval', $array);
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
                                new external_value(PARAM_INT, 'Module ID')
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
    
        // Check if the user with the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:view', $context);
    
        // Get the grade item record
        $grade_item = $DB->get_record('grade_items', array(
            'id' => $gradeid
        ));

        // Get the module record for the course module
        $module = $DB->get_record('modules', array(
            'name' => $grade_item->itemmodule
        ));
    
        // Get the course module record for the grade item
        $course_module = $DB->get_record('course_modules', array(
            'instance' => $grade_item->iteminstance,
            'module' => $module->id
        ));
    
        return array('itemid' => $course_module->id);
    }

    public static function get_course_item_id_for_grade_id_returns() {
        return new external_single_structure(
            array(
                'itemid' => new external_value(PARAM_INT, 'Course module ID', VALUE_OPTIONAL)
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
        // Get all roles
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
    
    public static function get_course_competencies($idnumber) {
        global $DB;
    
        // Get the competencies for the course
        $competencies = $DB->get_records('competency', array('idnumber' => $idnumber), '', 'id, shortname');
        $result = array();
        foreach ($competencies as $competency) {
            $result[] = array(
                'id' => $competency->id,
                'name' => $competency->shortname,
            );
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
   
    public static function update_course_parameters() {   
        return new external_function_parameters(
            array(
                'data' => new external_single_structure(
                    array(
                        'sections' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'Section ID', VALUE_REQUIRED),
                                    'sequence' => new external_multiple_structure(
                                        new external_value(PARAM_INT, 'Module ID', VALUE_REQUIRED)
                                    )
                                )
                            ),
                            VALUE_OPTIONAL
                        ),
                        'modules' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'Module ID', VALUE_REQUIRED),
                                    'section' => new external_value(PARAM_INT, 'Section ID', VALUE_REQUIRED),
                                    'indent' => new external_value(PARAM_INT, 'Indentation level', VALUE_REQUIRED),
                                    'c' => new external_value(PARAM_RAW, 'Availability conditions', VALUE_OPTIONAL),
                                    'lmsVisibility' => new external_value(PARAM_BOOL, 'Visibility', VALUE_REQUIRED),
                                    'order' => new external_value(PARAM_INT, 'Order', VALUE_OPTIONAL)
                                )
                            ),
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
        global $DB;
        //Check if the user has the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:update', $context);
        try {
            $transaction = $DB->start_delegated_transaction();
            // Update modules and sections
            foreach ($data->sections as $section) {
                $DB->set_field('course_sections', 'sequence', implode(',', $section['sequence']), array('id' => $section['id']));
            }
            foreach ($data->modules as $module) {
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
            // Update badges
            if ($data->badges !== null && is_array($data->badges) && count($data->badges) > 0) {
                foreach ($data->badges as $badgeData) {
                    if (!isset($badgeData['id']) || !isset($badgeData['conditions'])) {
                        // throw new moodle_exception('Invalid badge data');
                        return array('status' => false, 'error' => 'INVALID_BADGE_DATA');
                    }
                    $id = $badgeData['id'];
                    $newcriterias = $badgeData['conditions'];
                    if (!is_array($newcriterias)) {
                        // throw new moodle_exception('Invalid conditions data');
                        return array('status' => false, 'error' => 'INVALID_CONDITIONS_DATA');
                    }
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
                }
            }
            // Commit transaction
            $transaction->allow_commit();
            purge_all_caches();
            return array('status' => true, 'error' => '');
        } catch (\Exception $e) {
            // An error occurred, the changes will be reverted automatically.
            error_log('Fallo');
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
    
    
}
