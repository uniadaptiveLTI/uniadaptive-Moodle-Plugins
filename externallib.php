<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");

class local_uniadaptive_external extends external_api {

    //BADGES
    public static function get_course_badges_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
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
                'conditions' => $badgecriteria,
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
                    'conditions' => new external_multiple_structure(
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

    // Devuelve un array con los nombres de todos los módulos del curso que tienen calificaciones 
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
    public static function get_course_grade_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }
    
    public static function get_course_grade($courseid) {
        global $DB;
    
        // Check if the user has the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:view', $context);
    
        // Call the getCourseGrade function
        $grade_id = self::getCourseGrade($courseid);
    
        return array('grade_id' => $grade_id);
    }
    
    public static function get_course_grade_returns() {
        return new external_single_structure(
            array(
                'grade_id' => new external_value(PARAM_INT, 'Grade item ID', VALUE_OPTIONAL)
            )
        );
    }
    
    private static function getCourseGrade($courseid) {
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
                            'order' => new external_value(PARAM_INT, 'Order', VALUE_OPTIONAL),
                            'children' => new external_multiple_structure(
                                new external_value(PARAM_INT, 'Child ID'), '', VALUE_OPTIONAL
                            )
                        )
                    )
                )
            )
        );
    }

    public static function set_modules_list_by_sections($sections, $modules) {
        global $DB;

        // Check if the user has the required capability
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // Call the setModulesListBySections function
        $result = self::setModulesListBySections($sections, $modules);

        return array('result' => $result);
    }

    public static function set_modules_list_by_sections_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_BOOL, 'Result')
            )
        );
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
            // Ocurrió un error, los cambios serán revertidos automáticamente
            error_log($e);
            return false;
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
    
    
}
