<?php
namespace Trunk\EmailLibrary\EMail;
use Propel\Runtime\ActiveQuery\Criteria as Criteria;

/**
 * Class Message
 * TrunkNetworks EMail Helpers
 * @package Trunk\EmailLibrary\EMail
 */
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
