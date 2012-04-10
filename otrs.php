<?php

class otrs extends rcube_plugin
{
    // all task excluding 'login' and 'logout'
    public $task = '?(?!login|logout).*';
    // we've got no ajax handlers
    public $noajax = true;
    // skip frames
    public $noframe = true;

    private $list;
    
    function init()
    {
        $rcmail = rcmail::get_instance();
	$this->load_config('config.inc.php.dist');
        $this->add_texts('localization/', true);

        // register task
        $this->register_task('otrs');
	$this->register_action('index', array($this, 'action'));

        // add taskbar button
        $this->add_button(array(
	        'name' 	=> 'otrstask',
	        'class'	=> 'button-otrs',
	        'label'	=> 'otrs.btn_name',
	        'href'	=> './?_task=otrs',
            'onclick' => sprintf("return %s.command('otrs')", JS_OBJECT_NAME)
            ), 'taskbar');

        $rcmail->output->add_script(
            JS_OBJECT_NAME . ".enable_command('otrs', true);\n" .
            JS_OBJECT_NAME . ".otrs = function () { location.href = './?_task=otrs'; }",
            'head');
       
        // add style for taskbar button (must be here) and Help UI    
        $this->include_stylesheet("skins/default/otrs.css");
    }

    function action()
    {
        $rcmail = rcmail::get_instance();
	$rcmail->output->add_handlers(array('otrscontent' => array($this, 'content') ));
	$rcmail->output->set_title("Otrs");
	$rcmail->output->send('otrs.otrs');
    }


    function content($attrib)
    {
	$rcmail = rcmail::get_instance();
	$queues=$rcmail->config->get('otrs_queues');
	$otrs_rpc_url=$rcmail->config->get('otrs_rpc_url');
	$otrs_rpc_username=$rcmail->config->get('otrs_rpc_username');
	$otrs_rpc_password=$rcmail->config->get('otrs_rpc_password');
	
	$retval ='<B>'.$this->gettext('otrs_title').'</B><HR/><BR>';
	
	if ( (!isset($_POST['Dest'])) || (!isset($_POST['Subject'])) || (!isset($_POST['Body'])) || (!isset($_POST['PriorityID'])) )
	{
	    $retval .='<form method="POST" action="?_task=otrs">';
	    $retval .='<TABLE border=1>';
	    $retval .='<TR><TD><B>'.$this->gettext('otrs_queue').'</B></TD><TD>';
	    $retval .='<select name="Dest">';
	    for ($i=0;$i<sizeof($queues);$i++)
	    {
		$retval .='<option value="'.$queues[$i].'">'.$queues[$i].'</option>';
	    }
	    $retval .='</select></TD></TR>';
	    $retval .='<TR><TD><B>'.$this->gettext('otrs_subject').'</B></TD><TD><input type="text" name="Subject" value="" size="70"></TD></TR>';
	    $retval .='<TR><TD><B>'.$this->gettext('otrs_description').'</B></TD><TD><textarea name="Body" rows="15" cols="70" wrap="hard"></textarea></TD></TR>';
	    $retval .='<TR><TD><B>'.$this->gettext('otrs_priority').'</B></TD><TD><select name="PriorityID"><option value="1">1 '.$this->gettext('otrs_priority_1').'</option><option value="2">2 '.$this->gettext('otrs_priority_2').'</option><option selected value="3">3 '.$this->gettext('otrs_priority_3').'</option><option value="4">4 '.$this->gettext('otrs_priority_4').'</option><option value="5">5 '.$this->gettext('otrs_priority_5').'</option></select></TD></TR>';
	    $retval .='</TABLE>';
	    $retval .='<input type="submit" value="'.$this->gettext('otrs_submit').'" style="width: 100px" />';
	    $retval .='</form>';
	}
	else
	{
	    $Dest=$_POST['Dest'];
	    $Subject=$_POST['Subject'];
	    $Body=$_POST['Body'];
	    $PriorityID=$_POST['PriorityID'];

	    $client = new SoapClient(null, array('location'  =>
	    $otrs_rpc_url,
                                     'uri'       => "Core",
                                     'trace'     => 1,
                                     'login'     => $otrs_rpc_username,
                                     'password'  => $otrs_rpc_password,
                                     'style'     => SOAP_RPC,
                                     'use'       => SOAP_ENCODED));
	    $TicketID = $client->__soapCall("Dispatch", array($otrs_rpc_username, $otrs_rpc_password,
	    "TicketObject", "TicketCreate", 
	    "Title",        $Subject, 
	    "Queue",        $Dest, 
	    "Lock",         "Unlock", 
	    "PriorityID",   $PriorityID, 
	    "State",        "new", 
	    "CustomerUser", $rcmail->user->data['username'], 
	    "OwnerID",      1, 
	    "UserID",       1,
	    ));

	    $ArticleID = $client->__soapCall("Dispatch", 
	    array($otrs_rpc_username, $otrs_rpc_password,
	    "TicketObject",   "ArticleCreate",
	    "TicketID",       $TicketID,
	    "ArticleType",    "webrequest",
	    "SenderType",     "customer",
	    "HistoryType",    "WebRequestCustomer",
	    "HistoryComment", "created from Webmail",
	    "From",           $rcmail->user->data['username'],
	    "Subject",        $title,
	    "ContentType",    "text/plain; charset=UTF-8",
	    "Body",           $Body,
	    "UserID",         1,
	    "Loop",           0,
	    "AutoResponseType", 'auto reply',
	    "OrigHeader", array(
	            'From' => $rcmail->user->data['username'],
	            'To' => 'Postmaster',
	            'Subject' => $Subject,
	            'Body' => $Body
	        ),
	    ));

	    $TicketNr = $client->__soapCall("Dispatch", 
	    array($otrs_rpc_username, $otrs_rpc_password,
	    "TicketObject",   "TicketNumberLookup",
	    "TicketID",       $TicketID,
	    ));

	    $big_integer = 1202400000;
	    $Formatted_TicketNr = number_format($TicketNr, 0, '.', '');
	    $retval .='<div align=center><B>'.$this->gettext('otrs_ticket_saved').'</B><BR><BR>'.$this->gettext('otrs_ticket_details').'<BR>'.$this->gettext('otrs_ticket_number').' : '.$TicketNr.'<BR>'.$this->gettext('otrs_ticket_id').' : '.$TicketID.'<BR>'.$this->gettext('otrs_article_id').' : '.$ArticleID.'</div></BR>';
	    $retval .='<div align=center><input type=button onClick="location.href=\'?_task=mail\'" value=\''.$this->gettext('otrs_back').'\'></div>';

	}
	return $retval;
    }

}
