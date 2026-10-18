<?php

/**
 * Feature flags: the set of features that can be taken out of the site without a
 * deploy. One row per feature; the state is off, admins or everyone.
 */
class FeatureFlag extends AppModel
{
	public $useTable = 'feature_flags';
}
