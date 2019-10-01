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

	/**
	 * Default destination for the email in case no 'to' address is set
	 * @var string
	 */
	protected $default_to = '';

	/**
	 * Subject of the message
	 * @var string
	 */
	protected $subject = '';

	/**
	 * Body of the message
	 * @var string
	 */
	protected $body = '';

	/**
	 * Associative array of parameters to replace within the body
	 * @var array
	 */
	protected $params = array();

	/**
	 * Namespace the documents are under
	 * @var string
	 */
	private $namespace = null;

	/**
	 * Array of associative arrays [ 'filepath'=>'absolute path to the file', 'filename'=>'name of the file' ]
	 * @var array
	 */
	protected $attachments = [];

	public function __construct( $subject = '', $body = '', $to = [], $from = null, $attachments = null ) {
		$this->setSubject( $subject );
		$this->setBody( $body );
		$this->setFrom( $from );
		$this->setTo( $to );

		if( $attachments ) $this->setAttachments( $attachments );
	}

	/**
	 * Get the namespace used for document queries
	 * @return string
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * Set the namespace for document queries
	 * @param $namespace
	 * @return $this
	 */
	public function setNamespace( $namespace ) {
		$this->namespace = $namespace;
		return $this;
	}

	/**
	 * Set the default address to send the email to
	 * @param $contact
	 * @return $this
	 */
	public function setDefaultTo( $contact ) {
		$this->default_to = $contact;
		return $this;
	}

	/**
	 * Set the email address(es) to send the email to
	 * @param $contacts
	 * @return $this
	 * @throws \Exception
	 */
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
		return $this;
	}

	/**
	 * Return 'to' email address or fallback email address
	 * @return array|string
	 */
	public function getTo() {
		return !empty( $this->to ) ? $this->to : $this->default_to;
	}

	/**
	 * Add an email address to send the email to
	 * @param $contact_email
	 * @param string|null $contact_name
	 * @throws \Exception
	 */
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

	/**
	 * Set who the email is from (name and email)
	 * @param array|string $contact
	 * @return $this
	 */
	public function setFrom( $contact ) {
		if( is_array($contact) ) {
			foreach( $contact as $email=>$name ) {
				$this->setFromAddress($email);
				$this->setFromName($name);
			}
		} else {
			$this->setFromAddress( $contact );
		}
		return $this;
	}

	/**
	 * Set the from email address
	 * @param $address
	 * @return $this
	 */
	public function setFromAddress( $address ) {
		$this->from_address = $address;
		return $this;
	}

	/**
	 * Set the from name
	 * @param $name
	 * @return $this
	 */
	public function setFromName( $name ) {
		$this->from_name = $name;
		return $this;
	}

	/**
	 * Get the from email address
	 * @return null
	 */
	public function getFromAddress() {
		return $this->from_address;
	}

	/**
	 * Get the from email address
	 * @return null
	 */
	public function getFromName() {
		return $this->from_name;
	}

	/**
	 * Set the subject of the email
	 * @param $subject
	 * @return $this
	 */
	public function setSubject( $subject ) {
		$this->subject = $subject;
		return $this;
	}

	/**
	 * Get the subject of the email
	 * @return string
	 */
	public function getSubject() {
		return $this->subject;
	}

	/**
	 * Get the subject of the email with simple string replacement of variables
	 * @return mixed
	 */
	public function getReadySubject() {
		return $this->replace_placeholders( $this->subject , $this->params );
	}

	/**
	 * Set the body of the email
	 * @param $body
	 * @return $this
	 */
	public function setBody( $body ) {
		$this->body = $body;
		return $this;
	}

	/**
	 * Get the body of the email
	 * @return string
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * Get the body of the email with simple string replacement of variables
	 * @return mixed
	 */
	public function getReadyBody() {
		return $this->replace_placeholders( $this->body , $this->params );
	}

	/**
	 * Set an array of parameters used for replacements by transformers (or the simple string replacement)
	 * @param array $params
	 * @return $this
	 */
	public function setParams( array $params ) {
		$this->params = $params;
		return $this;
	}

	/**
	 * Get the array of parameters
	 * @return array
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * Set the attacments to add to the email
	 * @param $attachments
	 * @return $this
	 * @throws \Exception
	 */
	public function setAttachments( $attachments ) {
		if(!is_array( $attachments)) {
			throw new \Exception( "Attachments must be an array." );
		}
		$this->attachments = $attachments;
		return $this;
	}

	/**
	 * Get the attachments
	 * @return array
	 */
	public function getAttachments() {
		return $this->attachments;
	}

	/**
	 * Add an attachment to the email
	 * @param $attachment
	 * @return $this
	 * @throws \Exception
	 */
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

		return $this;
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

	/**
	 * Performs a simple replacement of placeholds in the provided message
	 * @param $message
	 * @param $options
	 * @return mixed
	 */
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
