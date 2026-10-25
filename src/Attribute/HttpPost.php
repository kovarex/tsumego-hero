<?php

namespace App\Attribute;

use Attribute;

/**
 * Marks an action as POST-only, mirroring CakePHP 5's Cake\Http\Attribute\HttpPost.
 * Enforced by AppController::enforceHttpMethodAttribute(). On migration to
 * CakePHP 5, swap `use App\Attribute\HttpPost;` for `use Cake\Http\Attribute\HttpPost;`
 * and delete the enforcement method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HttpPost {}
