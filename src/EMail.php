<?php
namespace Trunk\EmailLibrary\EMail;
use Propel\Runtime\ActiveQuery\Criteria as Criteria;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Trunk\EmailLibrary\EMail\transformer\TransformStringReplace;

/**
 * Class EMail
 * TrunkNetworks EMail Helpers
 * @package Trunk\EmailLibrary\EMail
 */
class EMail extends \Trunk\Wibbler\Modules\base {

	/**
	 * Default destination email address
	 * @var string
	 */
	private $to_address;
	/**
	 * Name of the company sending the email
	 * @var string
	 */
	private $company_name;

	/**
	 * Name of the function to retrieve the file
	 * @var string
	 */
	private $get_file_function = "get_file";

	/**
	 * Name of the host to connect to
	 * @var string
	 */
	private $smtp_host = "localhost";

	/**
	 * Port of the email system to connect to
	 * @var string
	 */
	private $smtp_port = "25";

	/**
	 * @var string
	 */
	private $smtp_method = null;

	/**
	 * @var string
	 */
	private $smtp_username = null;

	/**
	 * @var string
	 */
	private $smtp_password = null;

	/**
	 * @var string
	 */
	private $local_domain = null;

	public function __construct( array $options = null ) {
		parent::__construct();

		if ( $options ) {
			if ( isset( $options[ 'get_file_function' ] ) ) {
				$this->set_get_file_function( $options[ 'get_file_function' ], false );
			}
		}

		// Add the standard string replacement transformer to the default transformer group
		$this->add_transformer( "default", new TransformStringReplace() );
	}

	/**
	 * Set the smtp settings
	 * @param $host
	 * @param $port
	 */
	public function setSmtpSettings( $host, $port, $username = null, $password = null, $method = null, $local_domain = null ) {
		$this->smtp_host = $host;
		$this->smtp_port = $port;

		$this->smtp_username = $username;
		$this->smtp_password = $password;
		$this->smtp_method = $method;
		$this->local_domain = $local_domain;
	}

	/**
	 * Sets the function to use to retrieve the file paths
	 * @param $get_file_function
	 */
	function set_get_file_function( $get_file_function ) {
		$this->get_file_function = $get_file_function;
		return $this;
	}

	/**
	 * Sets some core details
	 * @param $emailToAddress
	 * @param $companyname
	 */
	function set_details( $emailToAddress, $companyname ) {
		$this->to_address = $emailToAddress;
		$this->company_name = $companyname;
		return $this;
	}

	/**
	 * Replace any placeholders with the parameter values
	 * @param $message
	 * @param $options
	 * @return mixed
	 */
	public function replace_placeholders( $message, $options ) {

		foreach ( $options as $key => $value ) {
			$message = str_replace( "{{" . $key . "}}", $value, $message );
		}
		$message = str_replace( "{{companyname}}", $this->company_name, $message );

		return $message;
	}

	/**
	 * @deprecated
	 * Deprecated! Actually send the message
	 * @param type $subject
	 * @param type $body
	 * @param type $from
	 * @param type $to
	 * @param type $data_array
	 * @param type $attachments
	 * @return type
	 */
	public function email_sendmessage( Message $email, $data_array = null ) {
		trigger_error("Function email_sendmessage is deprecated.", E_USER_NOTICE);

		#public function email_sendmessage( $subject, $body, $from, $to, $data_array = null, $attachments = null ) {
		$to = $email->getTo();
		$from_address = $email->getFromAddress();
		$from_name = $email->getFromName();
		$subject = $email->getSubject();
		$body = $email->getBody();
		$attachments = $email->getAttachments();

		// If we are not on production then use the test email address from the preferences
		if ( ENVIRONMENT != 'production' ) {
			// If this email is going to multiple addresses
			if ( is_array( $to ) ) {
				// Add the addresses the email was meant to go to in the subject line
				$subject .= " (" . implode( " - ", $to ) . ")";
			} else {
				// Add the address the email was meant to go to in the subject line
				$subject .= " (" . $to . ")";
			}

			// Change the destination to the preferences address
			$to = $this->to_address;
		}

		// Do the replacements of fields
		if ( $data_array !== null ) {
			$subject = $this->replace_placeholders( $subject, $data_array );
			$body = $this->replace_placeholders( $body, $data_array );
		}

		$message = new \Swift_Message();
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
			} else {
				// Iterate over the attachments
				foreach ( $attachments as $attachment ) {
					// Add each one to the email
					$sw_attachment = \Swift_Attachment::fromPath( $attachment[ 'filepath' ], 'application/octet-stream' );
					$sw_attachment->setFilename( $attachment[ 'filename' ] );
					$message->attach( $sw_attachment );
				}
			}
		}

		// Get the transport
		$transport = $this->_get_smtp_transport();

		$mailer = new \Swift_Mailer( $transport );

		$result = $mailer->send( $message, $errors );

		return $result == 0;
	}

	/**
	 * Send the email
	 * @param Message $email
	 * @param string $transformer_group
	 * @return bool
	 */
	public function sendmessage( Message $email, $transformer_group = 'default' ) {

		$to = $email->getTo();
		$from_address = $email->getFromAddress();
		$from_name = $email->getFromName();
		$subject = $email->getSubject();
		$attachments = $email->getAttachments();

		// If we are not on production then use the test email address from the preferences
		if ( ENVIRONMENT != 'production' ) {
			// If this email is going to multiple addresses
			if ( is_array( $to ) ) {
				// Add the addresses the email was meant to go to in the subject line
				$subject .= " (" . implode( " - ", $to ) . ")";
			} else {
				// Add the address the email was meant to go to in the subject line
				$subject .= " (" . $to . ")";
			}

			// Update the email subject
			$email->setSubject( $subject );

			// Change the destination to the preferences address
			$to = $this->to_address;
		}

		// If the transformer group exists
		if ( array_key_exists( $transformer_group, $this->transformer_groups ) ) {
			// Iterate over the transformers
			foreach( $this->transformer_groups[ $transformer_group ] as $transformer ) {
				// Transform the message
				$transformer->transform( $email );
			}
		}

		// Create a new symfony emailer email instance
		$emailMessage = new \Symfony\Component\Mime\Email();
		$emailMessage->to( $to )
			->from( new Address( $from_address, $from_name ) )
			->subject( $email->getSubject() );

		// Update the body - it may have inline images
		$body = $email->getBody();
		$body = $this->get_body_html( $email->getNamespace(), $emailMessage, $body );
		$emailMessage->html( $body );

		// If we have an attachment
		if ( !empty( $attachments ) ) {

			// If the 'filename' is part of the array this must be a single file
			if ( isset( $attachments[ 'filename' ] ) ) {
				$emailMessage->attachFromPath( $attachments[ 'filepath' ], $attachments[ 'filename' ], 'application/octet-stream');
			} else {
				// Iterate over the attachments
				foreach ( $attachments as $attachment ) {
					// Add each one to the email
					$emailMessage->attachFromPath( $attachments[ 'filepath' ], $attachments[ 'filename' ], 'application/octet-stream');
				}
			}
		}

		// Get the transport
		$transport = $this->_get_smtp_transport();

		try {
			// Get the result
			$result = $transport->send( $emailMessage );
			return true;
		}
		catch( TransportExceptionInterface $e ) {
			return false;
		}
	}

	/**
	 * Get the smtp transport for sending the messages over
	 * @return Transport
	 */
	protected function _get_smtp_transport() {

		// If there is a username
		if ( !empty( $this->smtp_username ) ) {
			// Create the dsn with username / password
			$dsn = sprintf( 'smtp://%s:%s@%s:%s', $this->smtp_username, $this->smtp_password, $this->smtp_host, $this->smtp_port );
		}
		else {
			// Create the 'simple' dsn
			$dsn = sprintf('smtp://%s:%s', $this->smtp_host, $this->smtp_port);
		}
		if ( !empty( $this->local_domain ) ) {
			$dsn .= '?local_domain=' . $this->local_domain;
		}

		return Transport::fromDsn( $dsn );
	}

	/**
	 * Embeds images if required
	 * @param \Symfony\Component\Mime\Email $message
	 * @return mixed|string
	 */
	private function get_body_html( $namespace, \Symfony\Component\Mime\Email &$message, $text ) {
		$document_ids = [ ];
		$matches = [ ];

		// Extract document ids
		preg_match_all( "{#[0-9]+#}", $text, $matches );
		foreach ( $matches[ 0 ] as $match ) {
			$document_ids[] = trim( $match, "{#}" );
		}

		preg_match_all( "/\[#[0-9]+#\]/", $text, $matches );
		foreach ( $matches[ 0 ] as $match ) {
			$document_ids[] = trim( $match, "[#]" );
		}

		if ( count( $document_ids ) > 0 ) {
			// get documents
			$doc_query = "\\" . $namespace . "\\DocumentQuery";
			$documents = $doc_query::create()
				->filterById( $document_ids, Criteria::IN )
				->find();

			$CIDs = [ ];
			// Embed all the documents and get their CIDs
			foreach ( $documents as $doc ) {

				// Get the function name to get the file path
				$doc_file_function = $this->get_file_function;
				// Get the path to the file
				$file = $doc->$doc_file_function();

				if ( is_file( $file ) ) {
					$message->embedFromPath( $file, 'file-id-' . $doc->getId() );
					$CIDs[ $doc->getId() ] = 'cid:file-id-' . $doc->getId();
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

		foreach ( $params as $key => $value ) {
			$message = str_replace( "{#" . $key . "#}", $value, $message );
			$message = str_replace( "[#" . $key . "#]", $value, $message );
		}

		return $message;

	}

	#region Transformation management
	/**
	 * Array of transformer groups
	 * @var array
	 */
	private $transformer_groups = [];

	/**
	 * Adds a transformer to the relevant group
	 * @param $key string
	 * @param $class object
	 */
	public function add_transformer( $key, $class ) {

		if ( !array_key_exists( $key, $this->transformer_groups ) ) {
			$this->transformer_groups[ $key ] = [];
		}

		$this->transformer_groups[ $key ][] = $class;
	}

	/**
	 * Removes the transformer group from the listed options
	 * @param $key
	 */
	public function clear_transformer_group( $key ) {
		$this->transformer_groups[ $key ] = [];
		unset( $this->transformer_groups[ $key ] );
	}
	#endregion
}
