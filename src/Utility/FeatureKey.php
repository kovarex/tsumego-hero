<?php

namespace App\Utility;

/**
 * The keys of the flags that can be switched from the feature_flags table.
 *
 * The value is the flag_key of the row and the same string a changelog fragment
 * writes as `flag:`. A call site passing a case cannot name a flag that does not
 * exist. Add a case together with the migration that seeds its row.
 */
enum FeatureKey: string
{
	case MistakeTraining = 'mistake-training';
}
