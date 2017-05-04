<?php
/**
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 */

require("../../config.php");
require_once("$CFG->dirroot/enrol/payfast/lib.php");

$id = required_param('id', PARAM_INT);

if (!$course = $DB->get_record("course", array("id"=>$id))) {
    redirect($CFG->wwwroot);
}

$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

require_login();

if (!empty($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
}

$fullname = format_string($course->fullname, true, array('context' => $context));

if (is_enrolled($context, NULL, '', true)) { // TODO: use real paypal check
    redirect($destination, get_string('paymentthanks', '', $fullname));

} else {   /// Somehow they aren't enrolled yet!  :-(
    $PAGE->set_url($destination);
    echo $OUTPUT->header();
    $a = new stdClass();
    $a->teacher = get_string('defaultcourseteacher');
    $a->fullname = $fullname;
    notice(get_string('paymentsorry', '', $a), $destination);
}


