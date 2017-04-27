<?php
namespace Trunk\EmailLibrary\EMail\transformer;
use Trunk\EmailLibrary\EMail\Message;

class TransformStringReplace extends BaseTransformer{

	public function transform( Message &$message ) {

		$body = $message->getBody();
		$subject = $message->getSubject();
		$params = $message->getParams();

		$message->setBody( $this->replace_placeholders( $body, $params ) );
		$message->setSubject( $this->replace_placeholders( $subject, $params ) );
	}

	private function replace_placeholders( $message, $options ) {

		foreach ( $options as $key => $value ) {
			$message = str_replace( "{{" . $key . "}}", $value, $message );
		}

		return $message;
	}
}