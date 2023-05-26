<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 * You (being anyone who is not Payfast (Pty) Ltd) may download and use this plugin / code
 * in your own website in conjunction with a registered and active Payfast account.
 * If your Payfast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin /
 * code or part thereof in any way.
 */

$string['assignrole'] = 'Assign role';
$string['businessemail'] = 'Payfast business email';
$string['businessemail_desc'] = 'The email address of your business Payfast account';
$string['cost'] = 'Enrol cost';
$string['costerror'] = 'The enrolment cost is not numeric';
$string['costorkey'] = 'Please choose one of the following methods of enrolment.';
$string['currency'] = 'Currency';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during Payfast enrolments';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid.
                               If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid,
                               starting with the moment the user is enrolled.
                               If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires.
                                 Please note that some user data and settings are purged
                                 from course during course unenrolment.';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['messageprovider:payfast_enrolment'] = 'Payfast enrolment messages';
$string['merchant_id'] = 'Payfast Merchant ID';
$string['merchant_id_desc'] = 'The Merchant ID provided by Payfast';
$string['merchant_key'] = 'Payfast Merchant Key';
$string['merchant_key_desc'] = 'The Merchant Key provided by Payfast';
$string['merchant_passphrase'] = 'Payfast Secure Passphrase';
$string['merchant_passphrase_desc'] = 'DO NOT SET THIS UNLESS YOU HAVE SET IT ON THE PAYFAST WEBSITE';
$string['nocost'] = 'There is no cost associated with enrolling in this course!';
$string['payfast:config'] = 'Configure Payfast enrol instances';
$string['payfast:manage'] = 'Manage enrolled users';
$string['payfast:unenrol'] = 'Unenrol users from course';
$string['payfast:unenrolself'] = 'Unenrol self from the course';
$string['payfast_live'] = 'Live Mode';
$string['payfast_test'] = 'Sandbox Mode';
$string['payfast_mode'] = 'Payfast Mode';
$string['payfast_mode_desc'] = 'Testing or Live Mode';
$string['payfast_debug'] = 'Debug Mode';
$string['payfast_debug_desc'] = 'Log ITN callbacks for debugging';
$string['payfastaccepted'] = 'Payfast payments accepted';
$string['pluginname'] = 'Payfast';
$string['pluginname_desc'] = 'The Payfast module allows you to set up paid courses.
                                If the cost for any course is zero, then students are not asked to pay for entry.
                                There is a site-wide cost that you set here as a default for the whole site and then
                               a course setting that you can set for each course individually.
                               The course cost overrides the site cost.';
$string['sendpaymentbutton'] = 'Send payment via Payfast';
$string['status'] = 'Allow Payfast enrolments';
$string['status_desc'] = 'Allow users to use Payfast to enrol into a course by default.';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
