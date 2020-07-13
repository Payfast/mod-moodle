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
 * Listens for Instant Payment Notification from PayFast
 *
 * This script waits for Payment notification from PayFast,
 * then double checks that data by sending it back to PayFast.
 * If PayFast verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol_payfast
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
require_once( "payfast_common.inc" );

// PayFast does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler('enrol_payfast_itn_exception_handler');

/// Keep out casual intruders
if ( empty( $_POST ) or !empty( $_GET ) )
{
    print_error("Sorry, you can not use the script that way.");
}
$tld = 'co.za';
$plugin = enrol_get_plugin('payfast');
define( 'PF_DEBUG', $plugin->get_config( 'payfast_debug' ) );

$pfError = false;
$pfErrMsg = '';
$pfDone = false;
$pfData = array();
$pfParamString = '';

pflog( 'PayFast ITN call received' );
$data = new stdClass();

foreach ( $_POST as $key => $value)
{
    $data->$key = $value;
}

$custom = explode( '-', $data->m_payment_id );
$data->userid           = (int)$custom[0];
$data->courseid         = (int)$custom[1];
$data->instanceid       = (int)$custom[2];
$data->payment_currency = 'ZAR';
$data->timeupdated      = time();

/// get the user and course records

if (! $user = $DB->get_record( "user", array( "id" => $data->userid ) ) )
{
    $pfError = true;
    $pfErrMsg .= "Not a valid user id \n";
}

if (! $course = $DB->get_record( "course", array( "id" => $data->courseid ) ) )
{
    $pfError = true;
    $pfErrMsg .= "Not a valid course id \n";
}

if (! $context = context_course::instance( $course->id, IGNORE_MISSING ) )
{
    $pfError = true;
    $pfErrMsg .= "Not a valid context id \n";
}

if (! $plugin_instance = $DB->get_record( "enrol", array( "id" => $data->instanceid, "status"=>0 ) ) )
{
    $pfError = true;
    $pfErrMsg .= "Not a valid instance id \n";
}


//// Notify PayFast that information has been received
if( !$pfError && !$pfDone )
{
    header( 'HTTP/1.0 200 OK' );
    flush();
}

//// Get data sent by PayFast
if( !$pfError && !$pfDone )
{
    pflog( 'Get posted data' );

    // Posted variables from ITN
    $pfData = pfGetData();
    $pfData['item_name'] = html_entity_decode( $pfData['item_name'] );
    $pfData['item_description'] = html_entity_decode( $pfData['item_description'] );
    pflog( 'PayFast Data: '. print_r( $pfData, true ) );

    if( $pfData === false )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_BAD_ACCESS;
    }
}

//// Verify security signature
if( !$pfError && !$pfDone )
{
    pflog( 'Verify security signature' );
    $passphrase = $plugin->get_config( 'merchant_passphrase' );
    $pfPassphrase = ( $plugin->get_config( 'payfast_mode' ) == 'test' && 
    ( empty( $plugin->get_config( 'merchant_id' ) ) || empty( $plugin->get_config( 'merchant_key' ) ) ) ) ? 'payfast' : ( !empty( $passphrase ) ? $passphrase : null );
    // If signature different, log for debugging
    if( !pfValidSignature( $pfData, $pfParamString, $pfPassphrase ) )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
    }
}

//// Verify source IP (If not in debug mode)
if( !$pfError && !$pfDone && !PF_DEBUG )
{
    pflog( 'Verify source IP' );

    if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
    }
}


//// Verify data received
if( !$pfError )
{
    pflog( 'Verify data received' );

    $pfHost = ( $plugin->get_config( 'payfast_mode' ) == 'live' ? 'www' : 'sandbox'  ) . '.payfast.' . $tld;
    $pfValid = pfValidData( $pfHost, $pfParamString );

    if( !$pfValid )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_BAD_ACCESS;
    }
}

//// Check data against internal order
if( !$pfError && !$pfDone )
{
    pflog( 'Check data against internal order' );

    if ( (float) $plugin_instance->cost <= 0 ) {
        $cost = (float) $plugin->get_config('cost');
    } else {
        $cost = (float) $plugin_instance->cost;
    }

    $cost = format_float( $cost, 2, false );
    // Check order amount
    if( !pfAmountsEqual( $pfData['amount_gross'], $cost ) )
    {
        $pfError = true;
        $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
    }
}

if( !$pfError && !$pfDone )
{
    if ( $existing = $DB->get_record( "enrol_payfast", array( "pf_payment_id" => $data->pf_payment_id ) ) )
    {   // Make sure this transaction doesn't exist already
        $pfErrMsg .= "Transaction $data->pf_payment_id is being repeated! \n" ;
        $pfError = true;
    }
    if ( $data->payment_currency != $plugin_instance->currency )
    {
        $pfErrMsg .= "Currency does not match course settings, received: " . $data->mc_currency . "\n";
        $pfError = true;
    }

    if ( !$user = $DB->get_record( 'user', array( 'id' => $data->userid ) ) )
    {   // Check that user exists
        $pfErrMsg .= "User $data->userid doesn't exist \n";
        $pfError = true;
    }

    if ( !$course = $DB->get_record( 'course', array( 'id'=> $data->courseid ) ) )
    { // Check that course exists
        $pfErrMsg .= "Course $data->courseid doesn't exist \n";
        $pfError = true;
    }
}


//// Check status and update order
if( !$pfError && !$pfDone )
{
    pflog( 'Check status and update order' );

    $transaction_id = $pfData['pf_payment_id'];

    switch( $pfData['payment_status'] )
    {
        case 'COMPLETE':
            pflog( '- Complete' );

            $coursecontext = context_course::instance($course->id, IGNORE_MISSING);


            if ($plugin_instance->enrolperiod) {
                $timestart = time();
                $timeend   = $timestart + $plugin_instance->enrolperiod;
            } else {
                $timestart = 0;
                $timeend   = 0;
            }

            // Enrol user
            $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);

            // Pass $view=true to filter hidden caps if the user cannot see them
            if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                '', '', '', '', false, true)) {
                $users = sort_by_roleassignment_authority($users, $context);
                $teacher = array_shift($users);
            } else {
                $teacher = false;
            }

            $mailstudents = $plugin->get_config('mailstudents');
            $mailteachers = $plugin->get_config('mailteachers');
            $mailadmins   = $plugin->get_config('mailadmins');
            $shortname = format_string($course->shortname, true, array('context' => $context));


            if (!empty($mailstudents)) {
                $a = new stdClass();
                $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
                $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

                $eventdata = new stdClass();
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'enrol_payfast';
                $eventdata->name              = 'payfast_enrolment';
                $eventdata->userfrom          = empty($teacher) ? get_admin() : $teacher;
                $eventdata->userto            = $user;
                $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '';
                $eventdata->smallmessage      = '';
                message_send($eventdata);

            }

            if (!empty($mailteachers) && !empty($teacher)) {
                $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                $a->user = fullname($user);

                $eventdata = new stdClass();
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'enrol_payfast';
                $eventdata->name              = 'payfast_enrolment';
                $eventdata->userfrom          = $user;
                $eventdata->userto            = $teacher;
                $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '';
                $eventdata->smallmessage      = '';
                message_send($eventdata);
            }

            if ( !empty( $mailadmins ) )
            {
                $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                $a->user = fullname($user);
                $admins = get_admins();
                foreach ($admins as $admin) {
                    $eventdata = new stdClass();
                    $eventdata->modulename        = 'moodle';
                    $eventdata->component         = 'enrol_payfast';
                    $eventdata->name              = 'payfast_enrolment';
                    $eventdata->userfrom          = $user;
                    $eventdata->userto            = $admin;
                    $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                    $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml   = '';
                    $eventdata->smallmessage      = '';
                    message_send($eventdata);
                }
            }
            $DB->insert_record("enrol_payfast", $data );


            break;

        case 'FAILED':
            pflog( '- Failed' );

            break;

        case 'PENDING':
            pflog( '- Pending' );

            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_payfast';
            $eventdata->name              = 'payfast_enrolment';
            $eventdata->userfrom          = get_admin();
            $eventdata->userto            = $user;
            $eventdata->subject           = "Moodle: PayFast payment";
            $eventdata->fullmessage       = "Your PayFast payment is pending.";
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);

            message_payfast_error_to_admin("Payment pending", $data );

            break;

        default:
            // If unknown status, do nothing (safest course of action)
            break;
    }

}
else
{
    $DB->insert_record( "enrol_payfast", $data, false);
    message_payfast_error_to_admin( "Received an invalid payment notification!! (Fake payment?)\n" . $pfErrMsg, $data);
    die( 'ERROR encountered, view the logs to debug.' );
}

exit;


//--- HELPER FUNCTIONS --------------------------------------------------------------------------------------


function message_payfast_error_to_admin($subject, $data) {
    echo $subject;
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    foreach ($data as $key => $value) {
        $message .= "$key => $value\n";
    }

    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_payfast';
    $eventdata->name              = 'payfast_enrolment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "PAYFAST ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    pflog( 'Error To Admin: ' . print_r( $eventdata, true ) );
    message_send($eventdata);

}

/**
 * Silent exception handler.
 *
 * @param Exception $ex
 * @return void - does not return. Terminates execution!
 */
function enrol_payfast_itn_exception_handler($ex) {
    $info = get_exception_info($ex);

    $logerrmsg = "enrol_payfast ITN exception handler: ".$info->message;
    $logerrmsg .= ' Debug: '.$info->debuginfo."\n".format_backtrace($info->backtrace, true);

    error_log($logerrmsg);

    exit(0);
}
