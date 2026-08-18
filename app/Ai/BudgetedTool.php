<?php

namespace App\Ai;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Stringable;

/** Delegasi tool dengan shared execution budget untuk call paralel maupun berurutan. */
final class BudgetedTool implements Tool
{
    public function __construct(
        private readonly Tool $delegate,
        private readonly Closure $consume,
    ) {}

    public function name(): string
    {
        return ToolNameResolver::resolve($this->delegate);
    }

    public function description(): Stringable|string
    {
        return $this->delegate->description();
    }

    public function handle(Request $request): Stringable|string
    {
        if (! ($this->consume)()) {
            return '{"status":"error","message":"tool call limit reached"}';
        }

        return $this->delegate->handle($request);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->delegate->schema($schema);
    }
}
