<?php

namespace App\Utility;

/**
 * Outcome of a single play, as reported by the play page.
 */
enum PlayOutcome: string
{
	/** The problem was solved without a single mistake in this play. */
	case SolvedClean = 'solved_clean';

	/** The problem was solved, but only after a mistake in this play. */
	case SolvedAfterMistake = 'solved_after_mistake';

	/** The play ended in a mistake. */
	case Failed = 'failed';

	/** Time mode ran out of time. */
	case TimedOut = 'timed_out';
}
