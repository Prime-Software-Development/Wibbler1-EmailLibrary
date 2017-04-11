<?php
namespace Trunk\EmailLibrary\EMail;
use Propel\Runtime\ActiveQuery\Criteria as Criteria;

/**
 * TrunkNetworks EMail Helpers
 */

class EMail extends \Trunk\Wibbler\Modules\base  {

	var $toAddress;
	var $companyName;

	function set_details( $emailToAddress, $companyname ) {

		$this->toAddress = $emailToAddress;
		$this->companyName = $companyname;

	}

	public function replace_placeholders( $message, $options ) {

		foreach ( $options as $key => $value ) {
			$message = str_replace( "{{" . $key . "}}", $value, $message );
		}
		$message = str_replace( "{{companyname}}", $this->companyName, $message );

		return $message;
	}

	/**
	 * 
	 * @param type $subject
	 * @param type $body
	 * @param type $from
	 * @param type $to
	 * @param type $data_array
	 * @param type $attachments
	 * @return type
	 */
	public function email_sendmessage( Message $email, $data_array = null ) {
	#public function email_sendmessage( $subject, $body, $from, $to, $data_array = null, $attachments = null ) {
		$to = $email->getTo();
		$from_address = $email->getFromAddress();
		$from_name = $email->getFromName();
		$subject = $email->getSubject();
		$body = $email->getBody();
		$attachments = $email->getAttachments();

		// If we are not on production then use the test email address from the preferences
		if (ENVIRONMENT != 'production') {
			// If this email is going to multiple addresses
			if ( is_array( $to ) ) {
				// Add the addresses the email was meant to go to in the subject line
				$subject .= " (" . implode( " - ", $to ) . ")";
			}
			else {
				// Add the address the email was meant to go to in the subject line
				$subject .= " (" . $to . ")";
			}

			// Change the destination to the preferences address
			$to = $this->toAddress;
		}

		$crlf = "\n";

		// Do the replacements of fields
		if ( $data_array !== null ) {
			$subject = $this->replace_placeholders( $subject, $data_array );
			$body = $this->replace_placeholders( $body, $data_array );
		}

		$message = \Swift_Message::newInstance();
		$message->setSubject( $subject );
		$message->setFrom( $from_address, $from_name );
		$message->setTo( $to );
		$message->setContentType( "text/html" );

		// Update the body - it may have inline images
		$body = $this->get_body_html( $email->getNamespace(), $message, $body );
		$message->setBody( $body );

		// If we have an attachment
		if ( !empty( $attachments ) ) {

			// If the 'filename' is part of the array this must be a single file
			if ( isset( $attachments[ 'filename' ] ) ) {
				$sw_attachment = \Swift_Attachment::fromPath( $attachments[ 'filepath' ], 'application/octet-stream' );
				$sw_attachment->setFilename( $attachments[ 'filename' ] );
				$message->attach( $sw_attachment );
			}
			else {
				// Iterate over the attachments
				foreach ( $attachments as $attachment ) {                                   
					// Add each one to the email
					$sw_attachment = \Swift_Attachment::fromPath( $attachment[ 'filepath' ], 'application/octet-stream' );
					$sw_attachment->setFilename( $attachment[ 'filename' ] );
					$message->attach( $sw_attachment );
				}
			}
		}

		$transport = \Swift_SmtpTransport::newInstance( 'localhost', 25 );
		$mailer = \Swift_Mailer::newInstance( $transport );

		$result = $mailer->send( $message, $errors );

		return $result == 0;
	}


	/**
	 * Gets the email footer to display - embedding images if required
	 * @param \Swift_Message $message
	 * @return mixed|string
	 */
	private function get_body_html( $namespace, \Swift_Message &$message, $text ){
		$document_ids = [];
		$matches = [];

		// Extract document ids
		preg_match_all( "{#[0-9]+#}", $text, $matches );
		foreach( $matches[0] as $match ) {
			$document_ids[] = trim( $match, "{#}" );
		}

		preg_match_all( "/\[#[0-9]+#\]/", $text, $matches );
		foreach( $matches[0] as $match ) {
			$document_ids[] = trim( $match, "[#]" );
		}

		if ( count($document_ids) > 0 ) {
			// get documents
			$doc_query = "\\" . $namespace . "\\DocumentQuery";
			$documents = $doc_query::create()
					->filterById( $document_ids, Criteria::IN )
					->find();

			$CIDs = [];
			// Embed all the documents and get their CIDs
			foreach( $documents as $doc ) {
				if( is_file( $doc->get_file() ) ) {
					$temp_cid = $message->embed( \Swift_Image::fromPath( $doc->get_file() ) );
					$CIDs[ $doc->getId() ] = $temp_cid;
				}
			}
			// Replace document ids with CIDs
			$text = EMail::replace_embedded_ids( $CIDs, $text );
		}

		return $text;
	}


	/**
	 * Replaces the variables in the message with the CID provided
	 * @param $params
	 * @param $message
	 * @return mixed
	 */
	static function replace_embedded_ids( $params, $message ) {

		foreach ($params as $key => $value) {
			$message = str_replace("{#" . $key . "#}", $value, $message);
			$message = str_replace("[#" . $key . "#]", $value, $message);
		}

		return $message;

	}

}

class Message {
	// Array of arrays [ 'email@address', 'contact name' ]
	protected $from_address = null;
	protected $from_name = null;
	// Array of arrays [ 'email@address', 'contact name' ]
	protected $to = [];

	protected $default_to = '';

	// String
	protected $subject = '';
	// String
	protected $body = '';

	protected $params = array();

	/**
	 * Namespace the documents are under
	 * @var string
	 */
	private $namespace = null;

	// Array of associative arrays [ 'filepath'=>'absolute path to the file', 'filename'=>'name of the file' ]
	protected $attachments = [];

	public function __construct( $subject = '', $body = '', $to = [], $from = null, $attachments = null ) {
		$this->setSubject( $subject );
		$this->setBody( $body );
		$this->setFrom( $from );
		$this->setTo( $to );

		if( $attachments ) $this->setAttachments( $attachments );
	}

	public function setNamespace( $namespace ) {
		$this->namespace = $namespace;
	}
	public function setDefaultTo( $contact ) {
		$this->default_to = $contact;
	}

	public function setTo( $contacts ) {
		if(is_array( $contacts )) {

			foreach( $contacts as $contact ) {
				if ( !filter_var( $contact, FILTER_VALIDATE_EMAIL) ) {
					throw new \Exception( "Invalid email address (" . $contact . ")" );
				}
			}

			$this->to = $contacts;
		} else {

			if ( !filter_var( $contacts, FILTER_VALIDATE_EMAIL) ) {
				throw new \Exception( "Invalid email address (" . $contacts . ")" );
			}

            $this->to = [];
			$this->to[] = $contacts;
		}
	}

	/**
	 * Return 'to' email address or fallback email address
	 * @return array|string
	 */
	public function getTo() {
		return !empty( $this->to ) ? $this->to : $this->default_to;
	}

	public function addTo( $contact_email, $contact_name = null ) {
		if ( !filter_var( $contact_email, FILTER_VALIDATE_EMAIL) ) {
			throw new \Exception( "Invalid email address (" . $contact_email . ")" );
		}

		if( $contact_name ) {
			$this->to[ $contact_email ] = $contact_name;
		} else {
			$this->to[] = $contact_email;
		}
	}

	public function setFrom( $contact ) {
		if( is_array($contact) ) {
			foreach( $contact as $email=>$name ) {
				$this->setFromAddress($email);
				$this->setFromName($name);
			}
		} else {
			$this->setFromAddress( $contact );
		}
	}

	public function setFromAddress( $address ) {
		$this->from_address = $address;
	}

	public function setFromName( $name ) {
		$this->from_name = $name;
	}

	public function getFromAddress() {
		return $this->from_address;
	}

	public function getNamespace() {
		return $this->namespace;
	}

	public function getFromName() {
		return $this->from_name;
	}

	public function setSubject( $subject ) {
		$this->subject = $subject;
	}

	public function getSubject() {
		return $this->subject;
	}

	public function getReadySubject() {
		return $this->replace_placeholders( $this->subject , $this->params );
	}

	public function setBody( $body ) {
		$this->body = $body;
	}

	public function getBody() {
		return $this->body;
	}

	public function getReadyBody() {
		return $this->replace_placeholders( $this->body , $this->params );
	}

	public function setParams( array $params ) {
		$this->params = $params;
		return $this;
	}

	public function getParams() {
		return $this->params;
	}

	public function setAttachments( $attachments ) {
		if(!is_array( $attachments)) {
			throw new \Exception( "Attachments must be an array." );
		}
		$this->attachments = $attachments;
	}

	public function getAttachments() {
		return $this->attachments;
	}

	public function addAttachment( $attachment ) {
		if( $attachment ) {
			$doc_type = "\\" . $this->namespace . "\\Document";
			if( $attachment instanceof $doc_type ) {
				$attachment = array(
					'filepath' => $attachment->get_file(),
					'filename' => $attachment->getDisplayFileName()
				);
			}
			if(!is_array( $attachment) || !isset($attachment['filepath']) || !isset($attachment['filename']) ) {
				throw new \Exception( "Attachment must be an array [ 'filepath'=>'Path to the File', 'filename'=>'Name of the File' ]." );
			}
			$this->attachments[] = $attachment;
		}
	}

	/**
	 * Add attachments from traversable collection ( e.g array, PropelObjectCollection )
	 * @param $attachments
	 * @throws \Exception
	 */
	public function addAttachments( $attachments ) {
		foreach( $attachments as $attachment ) {
			$this->addAttachment( $attachment );
		}
	}

	private function replace_placeholders( $message, $options ) {

		foreach ( $options as $key => $value ) {
			$message = str_replace( "{{" . $key . "}}", $value, $message );
		}

		return $message;
	}

	/**
	 * Create new Message object from \Tinc\PreferencesGroup values
	 * @param $group_code
	 * @return Message
	 */
	public static function createFromPrefGroup( $namespace, $group_code, $to_address = [], $from_address = null ) {

		$query_function = "\\" . $namespace . "\\PreferencesGroupQuery";
		$pref_group = $query_function::create()
			->findOneByCode( $group_code );

		if( $pref_group == null ) throw new \Exception( $group_code." does not exist" );
		if( empty($to_address) ) $to_address = $pref_group->getEMailFallbackAddress();

		$email = new self(
			$pref_group->getEMailSubject(),
			$pref_group->getEMailBody(),
			$to_address,
			$from_address ? $from_address : $pref_group->getEMailFrom()
		);
		$email->namespace = $namespace;
		$email->addAttachments( $pref_group->get_documents() );
		$email->setDefaultTo( $pref_group->getEMailFallbackAddress() );

		return $email;
	}
}
