<?php

namespace DataAccounting\Content;

use DataAccounting\Verification\Hasher;
use File;
use Message;
use ParserOptions;
use ParserOutput;
use TextContent;
use Title;

class FileVerificationContent extends TextContent {
	public const CONTENT_MODEL_FILE_VERIFICATION = 'file-verification';
	public const SLOT_ROLE_FILE_VERIFICATION = 'file-verification-slot';

	public function __construct( $text ) {
		parent::__construct( $text, static::CONTENT_MODEL_FILE_VERIFICATION );
	}

	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		if ( $this->mText === '' ) {
			$output->setText( Message::newFromKey( 'da-file-verification-no-hash' )->text() );
		} else {
			$output->setText(
				Message::newFromKey( 'da-file-verification-hash' )
					->params( trim( $this->getText() ) )
					->parseAsBlock()
			);
		}
	}

	/**
	 * @param File $file
	 * @return bool
	 */
	public function setHashFromFile( File $file ): bool {
		$path = $file->getLocalRefPath();
		if ( !$path || !file_exists( $path ) ) {
			return false;
		}

		$content = file_get_contents( $path );
		if ( !$content ) {
			return false;
		}

		$hasher = new Hasher();
		$this->mText = $hasher->getHashSum( $content );
		return true;
	}
}
