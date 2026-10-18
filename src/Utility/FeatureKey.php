<?php

namespace App\Utility;

/**
 * The keys of the flags that can be switched from the feature_flags table.
 *
 * The value is the flag_key of the row and the same string a changelog fragment
 * writes as `flag:`. Keeping them here means a call site cannot ask for a flag
 * that does not exist: a typo becomes an unknown enum case, not a silent "off".
 *
 * The name avoids the model that holds the rows (`ClassRegistry::init('FeatureFlag')`),
 * so a type and a table lookup never look alike in the same file.
 *
 * Add a case together with the migration that seeds its row.
 */
enum FeatureKey: string
{
	case MistakeTraining = 'mistake-training';
}
