<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 * You (being anyone who is not Payfast (Pty) Ltd) may download and use this plugin / code
 * in your own website in conjunction with a registered and active Payfast account.
 * If your Payfast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin /
 * code or part thereof in any way.
 */

require_once('../../config.php');
require_once('edit_form.php');

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT); // instanceid

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/payfast:config', $context);

$PAGE->set_url('/enrol/payfast/edit.php', array('courseid'=>$course->id, 'id'=>$instanceid));
$PAGE->set_pagelayout('admin');

$return = new moodle_url('/enrol/instances.php', array('id'=>$course->id));
if (!enrol_is_enabled('payfast')) {
    redirect($return);
}

$plugin = enrol_get_plugin('payfast');

if ($instanceid) {
    $instance = $DB->get_record(
        'enrol',
        array('courseid'=>$course->id, 'enrol'=>'payfast', 'id'=>$instanceid),
        '*',
        MUST_EXIST
    );
    $instance->cost = format_float($instance->cost, 2, true);
} else {
    require_capability('moodle/course:enrolconfig', $context);
    // no instance yet, we have to add new instance
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id       = null;
    $instance->courseid = $course->id;
}

$mForm = new enrol_payfast_edit_form(null, array($instance, $plugin, $context));

if ($mForm->is_cancelled()) {
    redirect($return);
} elseif ($data = $mForm->get_data()) {
    if ($instance->id) {
        $reset = ($instance->status != $data->status);

        $instance->status         = $data->status;
        $instance->name           = $data->name;
        $instance->cost           = unformat_float($data->cost);
        $instance->currency       = $data->currency;
        $instance->roleid         = $data->roleid;
        $instance->enrolperiod    = $data->enrolperiod;
        $instance->enrolstartdate = $data->enrolstartdate;
        $instance->enrolenddate   = $data->enrolenddate;
        $instance->timemodified   = time();
        $DB->update_record('enrol', $instance);

        if ($reset) {
            $context->mark_dirty();
        }
    } else {
        $fields = array(
            'status'=>$data->status,
            'name'=>$data->name,
            'cost'=>unformat_float($data->cost),
            'currency'=>$data->currency,
            'roleid'=>$data->roleid,
            'enrolperiod'=>$data->enrolperiod,
            'enrolstartdate'=>$data->enrolstartdate,
            'enrolenddate'=>$data->enrolenddate
        );
        $plugin->add_instance($course, $fields);
    }

    redirect($return);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_payfast'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_payfast'));
$mForm->display();
echo $OUTPUT->footer();
