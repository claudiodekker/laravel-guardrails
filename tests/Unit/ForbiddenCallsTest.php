<?php

use ClaudioDekker\LaravelGuardrails\ForbiddenCalls;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

it('flags a function by its name wherever it resolves', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $functions = ['app'];

        public function message(): string
        {
            return 'Inject the service through the constructor instead.';
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', <<<'PHP'
        <?php

        namespace App\Services;

        use function Acme\Container\app;

        app('cache');
        \app('cache');
        $this->app('cache');
        app_path('cache');
        PHP);

    expect($file->lines($rule))->toBe([7, 8]);
});

it('flags a function by its full name only, when the entry names its namespace', function (string $entry) {
    $rule = new class($entry) extends ForbiddenCalls
    {
        public function __construct(string $entry)
        {
            $this->functions = [$entry];

            parent::__construct();
        }

        public function message(): string
        {
            return 'Write it as an acceptance test.';
        }
    };

    $imported = TestFile::of(Kind::TESTS, 'tests/Feature/OrderTest.php', "<?php\n\nuse function Pest\\Laravel\\get;\n\nget('/orders');\n\\Pest\\Laravel\\get('/orders');\nget_class(\$order);\n");
    $bare = TestFile::of(Kind::TESTS, 'tests/Feature/CartTest.php', "<?php\n\nnamespace Tests\\Feature;\n\nget('/orders');\n");

    expect($imported->lines($rule))->toBe([5, 6])
        ->and($bare->lines($rule))->toBe([]);
})->with([
    'as written' => 'Pest\Laravel\get',
    'with a leading backslash' => '\Pest\Laravel\get',
]);

it('flags a method on any object, nullsafe and on $this alike', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $methods = ['table'];

        public function message(): string
        {
            return 'Go through the model.';
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', <<<'PHP'
        <?php

        $db->table('users');
        $db?->table('users');
        $this->table('users');
        table('users');
        Schema::table('users');
        $db->tableExists('users');
        PHP);

    expect($file->lines($rule))->toBe([3, 4, 5]);
});

it('flags a static call on the class the key names, written in full or imported', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $staticCalls = [
            'Illuminate\Support\Facades\DB' => ['table'],
        ];

        public function message(): string
        {
            return 'Go through the model.';
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', <<<'PHP'
        <?php

        namespace App\Services;

        use Illuminate\Support\Facades\DB;
        use Illuminate\Support\Facades\Schema;

        DB::table('users');
        \Illuminate\Support\Facades\DB::table('users');
        DB::select('select 1');
        Schema::table('users');
        $db->table('users');
        PHP);

    expect($file->lines($rule))->toBe([8, 9]);
});

it('flags a static call through the global alias when the key is bare', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $staticCalls = [
            'DB' => ['table'],
        ];

        public function message(): string
        {
            return 'Go through the model.';
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', <<<'PHP'
        <?php

        namespace App\Services;

        use Illuminate\Support\Facades\DB;

        \DB::table('users');
        DB::table('users');
        PHP);

    expect($file->lines($rule))->toBe([7]);
});

it('compares names ignoring case', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $functions = ['App'];

        protected array $methods = ['Table'];

        protected array $staticCalls = [
            '\db' => ['TABLE'],
        ];

        public function message(): string
        {
            return 'Go through the model.';
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', "<?php\n\napp('cache');\n\$db->table('users');\nDB::table('users');\n");

    expect($file->lines($rule))->toBe([3, 4, 5]);
});

it('describes the calls it forbids as code, naming the first three and counting the rest', function (ForbiddenCalls $rule, string $description) {
    expect($rule->description())->toBe($description)
        ->and($rule->explanation())->toBe($description);
})->with([
    'one' => [new class extends ForbiddenCalls
    {
        protected array $functions = ['app'];

        public function message(): string
        {
            return 'Inject it.';
        }
    }, 'No app().'],
    'two' => [new class extends ForbiddenCalls
    {
        protected array $functions = ['app', 'resolve'];

        public function message(): string
        {
            return 'Inject it.';
        }
    }, 'No app() or resolve().'],
    'three' => [new class extends ForbiddenCalls
    {
        protected array $methods = ['getConnection', 'table'];

        protected array $staticCalls = [
            'Illuminate\Support\Facades\DB' => ['table'],
        ];

        public function message(): string
        {
            return 'Go through the model.';
        }
    }, 'No ->getConnection(), ->table() or DB::table().'],
    'four' => [new class extends ForbiddenCalls
    {
        protected array $functions = ['\Pest\Laravel\get'];

        protected array $methods = ['getConnection', 'table'];

        protected array $staticCalls = [
            'Illuminate\Support\Facades\DB' => ['table'],
        ];

        public function message(): string
        {
            return 'Go through the model.';
        }
    }, 'No Pest\Laravel\get(), ->getConnection(), ->table() and 1 more.'],
]);

it('refuses a rule that forbids nothing', function () {
    expect(fn () => new class extends ForbiddenCalls
    {
        public function message(): string
        {
            return 'Inject it.';
        }
    })->toThrow(InvalidArgumentException::class, ' forbids nothing. List a function, a method or a static call.');
});

it('lets a rule add its own condition to isForbidden()', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $functions = ['app'];

        public function message(): string
        {
            return 'Inject the service and read the request instead.';
        }

        protected function isForbidden(Node $node): bool
        {
            return parent::isForbidden($node) || ($node instanceof Variable && $node->name === '_SERVER');
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', "<?php\n\napp('cache');\n\$ip = \$_SERVER['REMOTE_ADDR'];\n\$host = \$_ENV['HOST'];\n");

    expect($file->lines($rule))->toBe([3, 4]);
});

it('leaves a forbidden call alone when the change only edits an argument on another line', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $functions = ['app'];

        public function message(): string
        {
            return 'Inject the service through the constructor instead.';
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', "<?php\n\napp(\n    'cache',\n);\n")
        ->changedFrom("<?php\n\napp(\n    'config',\n);\n");

    expect($file->lines($rule))->toBe([]);
});

it('names the call alone when the rule gives no fix', function () {
    $rule = new class extends ForbiddenCalls
    {
        protected array $functions = ['app'];
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Prune.php', "<?php\n\napp('cache');\n");

    expect($file->reports($rule))->toEqual([['line' => 3, 'message' => 'app() is forbidden.']]);
});
