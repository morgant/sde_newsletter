<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Uncomment and edit this line to override:
$plugin['name'] = 'sde_newsletter';

$plugin['version'] = '0.5';
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
	
	$html_content_url = $text_content_url = $email_to = $email_from = $email_from_user = $email_from_other = $email_subject = $email_subject_other = $html_content = $text_content = $content_type = $email_body = $html_content_type = '';
	$users = array();
	$form_validated = true;
	$success = true;
	
	pagetop('sde_publish ', ($step == 'publish' ? 'Newsletter Published' : ''));
	
	// was the 'publish' button clicked?
	if ( $step == 'publish' )
	{
		// grab our submitted data
		$text_content_url = ps('text_content_url');
		$html_content_url = ps('html_content_url');
		$email_to = ps('email_to');
		$email_from = ps('email_from');
		$email_from_user = ps('email_from_user');
		$email_from_other = ps('email_from_other');
		$email_subject = ps('email_subject');
		$email_subject_other = ps('email_subject_other');
		
		// validate the form data
		if ( (!empty($html_content_url) || !empty($text_content_url)) && !empty($email_to) )
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
			
			if ( empty($html_content_url) && ($email_subject != 'other') )
			{
				$form_validated = false;
				print("<p>Error: If you're not going to supply HTML content, you must supply a subject.\n");
			}
		}
		else
		{
			$form_validated = false;
			print("<p>Error: At least one of the 'URL' fields and the 'To' field is required.</p>\n");
		}
		
		// build & send the email
		if ( $form_validated )
		{
			if ( !empty($html_content_url) )
			{
				// grab the data from the HTML URL
				$ch = curl_init($html_content_url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$html_content = curl_exec($ch);
			
				// was the html content grabbed successfully
				if ( curl_error($ch) != 0 )
				{
					$success = false;
					printf("<p>Error loading HTML URL content: %s.</p>\n", curl_error($ch));
				}
				else
				{
					// parse the content-type out of the HTML content
					if ( preg_match_all('/<meta\shttp-equiv=\"Content-Type\"\scontent=\"(.*)\"\s\/?>/i', $html_content, $results) > 0 )
					{
						//print_r($titles);
						$html_content_type = $results[1][0]; // the first result found
					}
					else
					{
						$html_content_type = "text/html";
					}

					// wrap the HTML content lines cleanly (at 78 chars, if possible)
					$lines = explode("\n", $html_content);
					for ($i = 0; $i < count($lines); $i++)
					{
						$lines[$i] = wordwrap($lines[$i], 78, "\n");
					}
					$html_content = implode("\n", $lines);
				}
				
				// close the curl handle
				curl_close($ch);
			}
			
			if ( !empty($text_content_url) )
			{
				// grab the data from the text URL
				$ch = curl_init($text_content_url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$text_content = html_entity_decode(curl_exec($ch), ENT_QUOTES);
			
				// was the text content grabbed successfully
				if ( curl_error($ch) != 0 )
				{
					$success = false;
					printf("<p>Error loading text URL content: %s.</p>\n", curl_error($ch));
				} else {
					// wrap the TEXT content lines cleanly (at 78 chars, if possible)
					$lines = explode("\n", $text_content);
					for ($i = 0; $i < count($lines); $i++)
					{
						$lines[$i] = wordwrap($lines[$i], 78, "\n");
					}
					$text_content = implode("\n", $lines);
				}
				
				// close the curl handle
				curl_close($ch);
			}
			
			if ( $success )
			{
				// put the email together
				if ( !empty($html_content) && !empty($text_content) )
				{
					// build a multi-part MIME email
					$boundary = "----=_Part_".rand(10000,99999)."_".rand(1000,9999)."_".rand(1000,9999).".".rand(1000,9999)."_";
					$content_type = "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"";
					
					
					// stitch the two contents together
					$email_body .= "--$boundary\r\nContent-Type: text/plain; charset=us-ascii\r\nContent-Transfer-Encoding: 7bit\r\n\r\n";
					$email_body .= $text_content."\r\n\r\n";
					$email_body .= "--$boundary\r\nContent-Type: $html_content_type\r\nContent-Transfer-Encoding: 7bit\r\n\r\n";
					$email_body .= $html_content."\r\n\r\n";
					$email_body .= "--$boundary--\r\n";
				}
				elseif ( !empty($text_content) )
				{
					// build the text-only email
					$content_type = "Content-Type: text/plain; charset=us-ascii";
					$email_body = $text_content;
				}
				elseif ( !empty($html_content) )
				{
					// build the HTML-only email
					$content_type = "Content-Type: $html_content_type";
					$email_body = $html_content;
				}
				
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
						if ( preg_match_all('/<title>(.*)<\/title>/i', $html_content, $titles) > 0 )
						{
							//print_r($titles);
							$subject = html_entity_decode($titles[1][0], ENT_QUOTES); // the first title found
						}
						break;
				}
				
				// build & send the email
				if ( mail($email_to, $subject, $email_body, "From: ".$from."\r\n".$content_type) )
				{
					print("<p>Email sent successfully.</p>\n");
				}
				else
				{
					print("<p>Error: Failed to send email.</p>\n");
				}
			}
		}
	}
	
	// build the form validation javascript
	?>
	<script type="text/javascript">
	function sde_newsletter_validate_submit(e)
	{
		var from_type, from, to;
		
		// determine the from address
		var email_from_options = $("input[name='email_from']");
		for ( i = 0; i < email_from_options.length; i++)
		{
		    if ( email_from_options[i].checked )
		    {
		    	from_type = email_from_options[i].value;
		    }
		}
		if ( from_type == "textpattern_user" )
		{
			from_user_select = $("select[name='email_from_user']")[0];
		    from = from_user_select.options[from_user_select.selectedIndex].value;
		}
		else
		{
		    from = $("input[name='email_from_other']")[0].value;
		}
		
		// determine the to address
		to = $("input[name='email_to']")[0].value;
		
		if ( !confirm("Are you sure you want to send the following email?\n\nFrom: "+from+"\nTo: "+to) )
		{
			e.preventDefault();
		}
	}
	</script>
	<?php
	
	// build the publish form
	$publish_form .= eInput('sde_newsletter')."\n";
	$publish_form .= sInput('publish')."\n";
	$publish_form .= "<fieldset><legend>Publish</legend>\n";
	$publish_form .= "<fieldset><legend>Content</legend>\n";
	$publish_form .= "<label for=\"html_content_url\">HTML Content URL:</label>&nbsp;".fInput('text', 'html_content_url', '')."<br />\n";
	$publish_form .= "<label for=\"text_content_url\">Text Content URL:</label>&nbsp;".fInput('text', 'text_content_url', '')."\n";
	$publish_form .= "</fieldset>\n";
	$publish_form .= "<fieldset><legend>To</legend>\n";
	$publish_form .= "<label for=\"email_to\">To:</label>&nbsp;".fInput('text', 'email_to', '')."\n";
	$publish_form .= "</fieldset>\n";
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
	$publish_form .= fInput('submit', 'publish_submit', 'Email Now', '', '', 'sde_newsletter_validate_submit(event)')."\n";
	$publish_form .= "</fieldset>\n";
	
	// output the publish form
	print(form($publish_form, 'width: 300px; margin-left: auto; margin-right: auto;'));
	
}

# --- END PLUGIN CODE ---

?>
