<?php

namespace App\Attribute;

use Attribute;

/**
 * Marks an action as PATCH-only, mirroring CakePHP 5's Cake\Http\Attribute\HttpPatch.
 * Enforced by AppController::enforceHttpMethodAttribute(). On migration to
 * CakePHP 5, swap `use App\Attribute\HttpPatch;` for `use Cake\Http\Attribute\HttpPatch;`
 * and delete the enforcement method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HttpPatch {}
