<?php

namespace App\Attribute;

use Attribute;

/**
 * Marks an action as DELETE-only, mirroring CakePHP 5's Cake\Http\Attribute\HttpDelete.
 * Enforced by AppController::enforceHttpMethodAttribute(). On migration to
 * CakePHP 5, swap `use App\Attribute\HttpDelete;` for `use Cake\Http\Attribute\HttpDelete;`
 * and delete the enforcement method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HttpDelete {}
