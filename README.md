mod-moodle
==========
Copyright (c) 2016 PayFast (Pty) Ltd

LICENSE:

This payment module is free software; you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published
by the Free Software Foundation; either version 3 of the License, or (at
your option) any later version.

This payment module is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
License for more details.

Please see http://www.opensource.org/licenses/ for a copy of the GNU Lesser
General Public License.

INTEGRATION:
Download the latest PayFast Moodle enrolment plugin from https://github.com/PayFast/mod-moodle/archive/master.zip

Unzip the file on your local drive and upload it to the publicly accessible Moodle installation, this should not overwrite any files on the website. [your moodle installation folder]/enroll/payfast

Login to your Moodle website as the admin, you will be presented with the 'Plugins Check' screen, press 'Update Moodle database now' button and then 'Continue'

You are now ready to insert your PayFast merchant ID and Key (these can be found by logging into your PayFast account and clicking on the Settings tab). Change the settings further to suit your needs. It's always advisable to do a test transaction in the Sandbox/Test site first.

Change the 'Enable PayFast Enrolments' to Yes

Save your changes and go 'Enable' the PayFast Enrolment plugin from the list of available enrolment plugins

In Administration>Course Administration>Users>Enrolment Methods go and add PayFast as an available enrolment method

