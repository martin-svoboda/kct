<?php

namespace Kct\Managers;

use KctDeps\Wpify\Snippets\CopyrightShortcode;
use KctDeps\Wpify\Snippets\RemoveAccentInFilenames;

final class SnippetsManager {
	public function __construct(
		RemoveAccentInFilenames $remove_accent_in_filenames,
		CopyrightShortcode $copyright_shortcode
	) {
	}
}
