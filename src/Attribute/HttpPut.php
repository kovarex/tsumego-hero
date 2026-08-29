<?php

namespace App\Attribute;

use Attribute;

/**
 * Marks an action as PUT-only, mirroring CakePHP 5's Cake\Http\Attribute\HttpPut.
 * Enforced by AppController::enforceHttpMethodAttribute(). On migration to
 * CakePHP 5, swap `use App\Attribute\HttpPut;` for `use Cake\Http\Attribute\HttpPut;`
 * and delete the enforcement method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HttpPut {}
