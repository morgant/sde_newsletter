<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Uncomment and edit this line to override:
$plugin['name'] = 'sde_newsletter';

$plugin['version'] = '0.1';
$plugin['author'] = 'Small Dog Electronics, Inc.';
$plugin['author_uri'] = 'http://www.smalldog.com/';
$plugin['description'] = 'Implements an admin-side interface for sending Textpattern pages as email newsletters.';

// Plugin types:
// 0 = regular plugin; loaded on the public web side only
// 1 = admin plugin; loaded on both the public and admin side
// 2 = library; loaded only when include_plugin() or require_plugin() is called
$plugin['type'] = 1; 


@include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---



# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---


/* 
 * Admin Interface
 */
if ( @txpinterface == 'admin' )
{
	// only publishers & managing editrs should have permission to use this plug-in
	add_privs('sde_newsletter', '1,2');
	
	// add the tab & register the callback
	register_tab('extensions', 'sde_newsletter', 'sde_newsletter');
	register_callback('sde_newsletter_admin_tab', 'sde_newsletter');
}

function sde_newsletter_admin_tab($event, $step)
{
	$publish_form = '';
	
	$content_url = $email_to = $email_from = $email_from_user = $email_from_other = $email_subject = $email_subject_other = '';
	$users = array();
	$form_validated = true;
	
	pagetop('sde_publish ', ($step == 'publish' ? 'Newsletter Published' : ''));
	
	// was the 'publish' button clicked?
	if ( $step == 'publish' )
	{
		// grab our submitted data
		$content_url = ps('content_url');
		$email_to = ps('email_to');
		$email_from = ps('email_from');
		$email_from_user = ps('email_from_user');
		$email_from_other = ps('email_from_other');
		$email_subject = ps('email_subject');
		$email_subject_other = ps('email_subject_other');
		
		// validate the form data
		if ( !empty($content_url) && !empty($email_to) )
		{
			if ( $email_from == 'other' )
			{
				if ( empty($email_from_other) )
				{
					$form_validated = false;
					print("<p>Error: The 'Other' field for the 'From' address was empty.</p>\n");
				}
			}
			
			if ( $email_subject == 'other' )
			{
				if ( empty($email_subject_other) )
				{
					$form_validated = false;
					print("<p>Error: The 'Other' field for the 'Subject' was empty.</p>\n");
				}
			}
		}
		else
		{
			$form_validated = false;
			print("<p>Error: The 'URL' and 'To' fields are required.</p>\n");
		}
		
		// build & send the email
		if ( $form_validated )
		{
			// grab the data from the URL
			$ch = curl_init($content_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$page_content = curl_exec($ch);
			
			// was the page content grabbed successfully
			if ( curl_error($ch) == 0 )
			{
				// build the from address
				switch( $email_from )
				{
					case 'other':
						$from = $email_from_other;
						break;
					case 'textpattern_user':
						$from = safe_field('RealName', 'txp_users', "email = '$email_from_user'").'<'.$email_from_user.'>';
						break;
				}
				
				// build the email subject
				$subject = '';
				switch( $email_subject )
				{
					case 'other':
						$subject = $email_subject_other;
						break;
					case 'page_title':
						// parse the subject out of the HTML content
						if ( preg_match_all('/<title>(.*)<\/title>/i', $page_content, $titles) > 0 )
						{
							//print_r($titles);
							$subject = $titles[1][0]; // the first title found
						}
						break;
				}
				
				// build & send the email
				if ( mail($email_to, $subject, $page_content, "From: ".$from."\r\nContent-type: text/html") )
				{
					print("<p>Email sent successfully.</p>\n");
				}
				else
				{
					print("<p>Error: Failed to send email.</p>\n");
				}
			}
			else
			{
				printf("<p>Error loading URL content: %s.</p>\n", curl_error($ch));
			}
			
			// close the curl handle
			curl_close($ch);
		}
	}
	
	// build the publish form
	$publish_form .= eInput('sde_newsletter')."\n";
	$publish_form .= sInput('publish')."\n";
	$publish_form .= "<fieldset><legend>Publish</legend>\n";
	$publish_form .= "<p><label for=\"content_url\">URL:</label>&nbsp;".fInput('text', 'content_url', '')."</p>\n";
	$publish_form .= "<p><label for=\"email_to\">To:</label>&nbsp;".fInput('text', 'email_to', '')."</p>\n";
	$publish_form .= "<fieldset><legend>From:</legend>\n";
	$publish_form .= radio('email_from', 'textpattern_user', 1)."\n";
	$publish_form .= "<label for=\"email_from\">Textpattern user:&nbsp;\n";
	$textpattern_users = safe_rows('email, RealName', 'txp_users', 'privs > 0');
	foreach ( $textpattern_users as $row )
	{
		$users[$row['email']] = $row['RealName'];
	}
	$publish_form .= selectInput('email_from_user', $users, false);
	$publish_form .= "</label><br />\n";
	$publish_form .= radio('email_from', 'other', 0)."\n";
	$publish_form .= "<label for=\"email_from\">Other:&nbsp;".fInput('text', 'email_from_other', '')."</label>\n";
	$publish_form .= "</fieldset>\n";
	$publish_form .= "<fieldset><legend>Subject:</legend>\n";
	$publish_form .= radio('email_subject', 'page_title', 1)."&nbsp;<label>Page title</label><br />\n";
	$publish_form .= radio('email_subject', 'other', 0)."&nbsp;<label>Other:&nbsp;".fInput('text', 'email_subject_other', '')."</label>\n";
	$publish_form .= "</fieldset>\n";
	$publish_form .= fInput('submit', 'publish_submit', 'Email Now')."\n";
	$publish_form .= "</fieldset>\n";
	
	// output the publish form
	print(form($publish_form, 'width: 300px; margin-left: auto; margin-right: auto;'));
	
}

# --- END PLUGIN CODE ---

?>