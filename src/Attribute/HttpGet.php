<?php

namespace App\Attribute;

use Attribute;

/**
 * Marks an action as GET-only, mirroring CakePHP 5's Cake\Http\Attribute\HttpGet.
 * Enforced by AppController::enforceHttpMethodAttribute(). On migration to
 * CakePHP 5, swap `use App\Attribute\HttpGet;` for `use Cake\Http\Attribute\HttpGet;`
 * and delete the enforcement method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HttpGet {}
