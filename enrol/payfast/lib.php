<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 * You (being anyone who is not Payfast (Pty) Ltd) may download and use this plugin / code
 * in your own website in conjunction with a registered and active Payfast account.
 * If your Payfast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin /
 * code or part thereof in any way.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Payfast enrolment plugin implementation.
 * @author  Eugene Venter - based on code by Martin Dougiamas and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class enrol_payfast_plugin extends enrol_plugin
{
    public const PAYFAST_CONFIG_LITERAL = 'enrol/payfast:config';
    public const PAYFAST_EDIT_LITERAL = '/enrol/payfast/edit.php';

    public function get_currencies()
    {
        $codes = array(
            'ZAR');
        $currencies = array();
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }

        return $currencies;
    }

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances)
    {
        return array(new pix_icon('icon', get_string('pluginname', 'enrol_payfast'), 'enrol_payfast'));
    }

    public function roles_protected()
    {
        // users with role assign cap may tweak the roles later
        return false;
    }

    public function allow_unenrol(stdClass $instance)
    {
        // users with unenrol cap may unenrol other users manually - requires enrol/payfast:unenrol
        return true;
    }

    public function allow_manage(stdClass $instance)
    {
        // users with manage cap may tweak period and status - requires enrol/payfast:manage
        return true;
    }

    public function show_enrolme_link(stdClass $instance)
    {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    /**
     * Sets up navigation entries.
     *
     * @param object $instance
     * @return void
     */
    public function add_course_navigation($instancesnode, stdClass $instance)
    {
        if ($instance->enrol !== 'payfast') {
            throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability(self::PAYFAST_CONFIG_LITERAL, $context)) {
            $managelink = new moodle_url(
                self::PAYFAST_EDIT_LITERAL,
                array('courseid'=>$instance->courseid, 'id'=>$instance->id)
            );
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance)
    {
        global $OUTPUT;

        if ($instance->enrol !== 'payfast') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability(self::PAYFAST_CONFIG_LITERAL, $context)) {
            $editlink = new moodle_url(
                "/enrol/payfast/edit.php",
                array('courseid'=>$instance->courseid, 'id'=>$instance->id)
            );
            $icons[] = $OUTPUT->action_icon(
                $editlink,
                new pix_icon('t/edit', get_string('edit'), 'core', array('class' => 'iconsmall'))
            );
        }

        return $icons;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid)
    {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability(
            'moodle/course:enrolconfig',
            $context
        )
            || !has_capability(self::PAYFAST_CONFIG_LITERAL, $context)
        ) {
            return null;
        }

        // multiple instances supported - different cost for different roles
        return new moodle_url(self::PAYFAST_EDIT_LITERAL, array('courseid'=>$courseid));
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance)
    {
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;

        ob_start();

        if (
            $DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))
            || $instance->enrolstartdate != 0 && $instance->enrolstartdate > time()
            || $instance->enrolenddate != 0 && $instance->enrolenddate < time()
        ) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id'=>$instance->courseid));
        $context = context_course::instance($course->id);

        $shortname = format_string($course->shortname, true, array('context' => $context));

        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability(
            $context,
            'moodle/course:update',
            'u.*',
            'u.id ASC',
            '',
            '',
            '',
            '',
            false,
            true
        )) {
            $users = sort_by_roleassignment_authority($users, $context);
        }

        if ((float) $instance->cost <= 0) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
            echo '<p>'.get_string('nocost', 'enrol_payfast').'</p>';
        } else {
            // Calculate localised and "." cost, make sure we send Payfast the same value,
            // please note Payfast expects amount with 2 decimal places and "." separator.
            $localisedcost = format_float($cost, 2, true);
            $cost = format_float($cost, 2, false);

            if (isguestuser()) { // force login only for guest user, not real users with guest role
                if (empty($CFG->loginhttps)) {
                    $wwwroot = $CFG->wwwroot;
                } else {
                    // This actually is not so secure ;-), 'cause we're
                    // in unencrypted connection...
                    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
                }
                echo '<div class="mdl-align"><p>'.get_string('paymentrequired').'</p>';
                echo '<p><b>'.get_string('cost').": $instance->currency $localisedcost".'</b></p>';
                echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
                echo '</div>';
            } else {
                //Sanitise some fields before building the Payfast form
                $courseFullName  = format_string($course->fullname, true, array('context'=>$context));
                $courseShortName = $shortname;
                $userFirstName   = $USER->firstname;
                $userLastName    = $USER->lastname;
                $instanceName    = $this->get_instance_name($instance);

                if (
                    $this->get_config('payfast_mode') == 'test'
                    && empty($this->get_config('merchant_id'))
                    || empty($this->get_config('merchant_key'))
                ) {
                    $payfasturl   = 'https://sandbox.payfast.co.za/eng/process';
                    $merchant_id  = '10004002';
                    $merchant_key = 'q1cd2rdny4a53';
                    $passphrase   = 'payfast';
                } else {
                    $this->get_config('payfast_mode') == 'live'
                        ? $payfasturl = 'https://www.payfast.co.za/eng/process'
                        : $payfasturl = 'https://sandbox.payfast.co.za/eng/process';
                    $merchant_id  = $this->get_config('merchant_id');
                    $merchant_key = $this->get_config('merchant_key');
                    $passphrase   = $this->get_config('merchant_passphrase');
                }

                $formArray = array(
                    'merchant_id' => $merchant_id ,
                    'merchant_key' => $merchant_key ,
                    'return_url'=> $CFG->wwwroot.'/enrol/payfast/return.php?id='.$course->id,
                    'cancel_url' => $CFG->wwwroot,
                    'notify_url' => $CFG->wwwroot.'/enrol/payfast/itn.php',
                    'name_first' => $userFirstName,
                    'name_last' => $userLastName,
                    'email_address'=> $USER->email,
                    'm_payment_id' => "{$USER->id}-{$course->id}-{$instance->id}",
                    'amount' => $cost,
                    'item_name' => html_entity_decode($courseShortName),
                    'item_description' => html_entity_decode($courseFullName)
                );

                $secureString = '';
                foreach ($formArray as $k => $v) {
                    $secureString .= $k.'='.urlencode(trim($v)).'&';
                }

                if (!empty($passphrase)) {
                    $secureString = $secureString.'passphrase=' . urlencode($passphrase);
                } else {
                    $secureString = substr($secureString, 0, -1);
                }

                $securityHash = md5($secureString);
                $formArray['signature'] = $securityHash;
                $formArray['user_agent'] = 'Moodle 2.9';

                include_once($CFG->dirroot.'/enrol/payfast/enrol.html');
            }
        }

        return $OUTPUT->box(ob_get_clean());
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid)
    {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            );
        }
        if ($merge && $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(
        restore_enrolments_structure_step $step,
        $data,
        $instance,
        $userid,
        $oldinstancestatus
    ) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue)
    {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/payfast:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/delete', ''),
                get_string('unenrol', 'enrol'),
                $url,
                array('class'=>'unenrollink', 'rel'=>$ue->id)
            );
        }
        if ($this->allow_manage($instance) && has_capability("enrol/payfast:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/edit', ''),
                get_string('edit'),
                $url,
                array('class'=>'editenrollink', 'rel'=>$ue->id)
            );
        }
        return $actions;
    }

    public function cron()
    {
        $trace = new text_progress_trace();
        $this->process_expirations($trace);
    }

    /**
     * Execute synchronisation.
     * @param progress_trace $trace
     * @return int exit code, 0 means ok
     */
    public function sync(progress_trace $trace)
    {
        $this->process_expirations($trace);
        return 0;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance)
    {
        $context = context_course::instance($instance->courseid);
        return has_capability(self::PAYFAST_CONFIG_LITERAL, $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance)
    {
        $context = context_course::instance($instance->courseid);
        return has_capability(self::PAYFAST_CONFIG_LITERAL, $context);
    }
}
