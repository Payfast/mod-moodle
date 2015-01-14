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
 * Paypal enrolments plugin settings and presets.
 *
 * @package    enrol_payfast
 * @copyright  2010 Eugene Venter
 * @author     Eugene Venter - based on code by Petr Skoda and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_payfast_settings', '', get_string('pluginname_desc', 'enrol_payfast')));

    $settings->add(new admin_setting_configtext('enrol_payfast/merchant_id', get_string( 'merchant_id', 'enrol_payfast'), get_string('merchant_id_desc', 'enrol_payfast'), '', PARAM_INT));

    $settings->add(new admin_setting_configtext('enrol_payfast/merchant_key', get_string( 'merchant_key', 'enrol_payfast'), get_string('merchant_key_desc', 'enrol_payfast'), '', PARAM_ALPHANUM));

    $settings->add(new admin_setting_configtext('enrol_payfast/merchant_passphrase', get_string('merchant_passphrase', 'enrol_payfast'), get_string('merchant_passphrase_desc', 'enrol_payfast'), '', '/^[a-zA-Z0-9-\(\)@.,_:#\/ ]*$/'));

    $options = array(
        'test'  => get_string('payfast_test', 'enrol_payfast'),
        'live'  => get_string('payfast_live', 'enrol_payfast')
    );
    $settings->add(new admin_setting_configselect('enrol_payfast/payfast_mode', get_string('payfast_mode', 'enrol_payfast'), get_string('payfast_mode_desc', 'enrol_payfast'), 'test', $options));

    $settings->add(new admin_setting_configcheckbox('enrol_payfast/payfast_debug', get_string('payfast_debug', 'enrol_payfast'),  get_string('payfast_debug_desc', 'enrol_payfast'), 1));

    $settings->add(new admin_setting_configcheckbox('enrol_payfast/mailstudents', get_string('mailstudents', 'enrol_payfast'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_payfast/mailteachers', get_string('mailteachers', 'enrol_payfast'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_payfast/mailadmins', get_string('mailadmins', 'enrol_payfast'), '', 0));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_payfast/expiredaction', get_string('expiredaction', 'enrol_payfast'), get_string('expiredaction_help', 'enrol_payfast'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_payfast_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_payfast/status',
        get_string('status', 'enrol_payfast'), get_string('status_desc', 'enrol_payfast'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_payfast/cost', get_string('cost', 'enrol_payfast'), '', 0, PARAM_FLOAT, 4));

    $payfastcurrencies = enrol_get_plugin('payfast')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_payfast/currency', get_string('currency', 'enrol_payfast'), '', 'ZAR', $payfastcurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_payfast/roleid',
            get_string('defaultrole', 'enrol_payfast'), get_string('defaultrole_desc', 'enrol_payfast'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_payfast/enrolperiod',
        get_string('enrolperiod', 'enrol_payfast'), get_string('enrolperiod_desc', 'enrol_payfast'), 0));
}
