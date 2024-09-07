<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Command;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Sync\Command\GetSyncEntity;
use Salient\Tests\Sync\Entity\Comment;
use Salient\Tests\Sync\Entity\Unimplemented;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\External\Provider\MockProvider as ExternalMockProvider;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\Sync\Provider\MockProvider;
use stdClass;

/**
 * @covers \Salient\Sync\Command\GetSyncEntity
 * @covers \Salient\Sync\Command\AbstractSyncCommand
 */
final class GetSyncEntityTest extends SyncCommandTestCase
{
    /**
     * @dataProvider runProvider
     * @backupGlobals enabled
     *
     * @param string[] $args
     * @param array<string,int>|null $httpRequestCount
     * @param class-string[] $providers
     */
    public function testRun(
        string $output,
        int $exitStatus,
        array $args,
        ?array $httpRequestCount = null,
        int $runs = 1,
        array $providers = [],
        bool $providerless = false
    ): void {
        $this->Providers = $providers;
        $this->Providerless = $providerless;
        $this->assertCommandProduces(
            static::normaliseConsoleOutput($output),
            $exitStatus,
            GetSyncEntity::class,
            $args,
            [],
            true,
            false,
            null,
            $runs,
            $httpRequestCount === null
                ? null
                : static function ($app) use ($httpRequestCount): void {
                    static::assertSameHttpRequests($httpRequestCount, $app);
                },
            true,
        );
    }

    /**
     * @return array<array{string,int,string[],3?:array<string,int>|null,4?:int,5?:class-string[],6?:bool}>
     */
    public static function runProvider(): array
    {
        return [
            [
                <<<'EOF'
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User
id,name,username,email,address,phone,company,tasks,posts,albums
1,"Leanne Graham",Bret,Sincere@april.biz,"{""street"":""Kulas Light"",""suite"":""Apt. 556"",""city"":""Gwenborough"",""zipcode"":""92998-3874"",""geo"":{""lat"":""-37.3159"",""lng"":""81.1496""}}","1-770-736-8031 x56442","{""name"":""Romaguera-Crona"",""catchPhrase"":""Multi-layered client-server neural-net"",""bs"":""harness real-time e-markets""}",,,
2,"Ervin Howell",Antonette,Shanna@melissa.tv,"{""street"":""Victor Plains"",""suite"":""Suite 879"",""city"":""Wisokyburgh"",""zipcode"":""90566-7771"",""geo"":{""lat"":""-43.9509"",""lng"":""-34.4618""}}","010-692-6593 x09125","{""name"":""Deckow-Crist"",""catchPhrase"":""Proactive didactic contingency"",""bs"":""synergize scalable supply-chains""}",,,
3,"Clementine Bauch",Samantha,Nathan@yesenia.net,"{""street"":""Douglas Extension"",""suite"":""Suite 847"",""city"":""McKenziehaven"",""zipcode"":""59590-4157"",""geo"":{""lat"":""-68.6102"",""lng"":""-47.0653""}}",1-463-123-4447,"{""name"":""Romaguera-Jacobson"",""catchPhrase"":""Face to face bifurcated interface"",""bs"":""e-enable strategic applications""}",,,
4,"Patricia Lebsack",Karianne,Julianne.OConner@kory.org,"{""street"":""Hoeger Mall"",""suite"":""Apt. 692"",""city"":""South Elvis"",""zipcode"":""53919-4257"",""geo"":{""lat"":""29.4572"",""lng"":""-164.2990""}}","493-170-9623 x156","{""name"":""Robel-Corkery"",""catchPhrase"":""Multi-tiered zero tolerance productivity"",""bs"":""transition cutting-edge web services""}",,,
5,"Chelsey Dietrich",Kamren,Lucio_Hettinger@annie.ca,"{""street"":""Skiles Walks"",""suite"":""Suite 351"",""city"":""Roscoeview"",""zipcode"":""33263"",""geo"":{""lat"":""-31.8129"",""lng"":""62.5342""}}",(254)954-1289,"{""name"":""Keebler LLC"",""catchPhrase"":""User-centric fault-tolerant solution"",""bs"":""revolutionize end-to-end systems""}",,,
6,"Mrs. Dennis Schulist",Leopoldo_Corkery,Karley_Dach@jasper.info,"{""street"":""Norberto Crossing"",""suite"":""Apt. 950"",""city"":""South Christy"",""zipcode"":""23505-1337"",""geo"":{""lat"":""-71.4197"",""lng"":""71.7478""}}","1-477-935-8478 x6430","{""name"":""Considine-Lockman"",""catchPhrase"":""Synchronised bottom-line interface"",""bs"":""e-enable innovative applications""}",,,
7,"Kurtis Weissnat",Elwyn.Skiles,Telly.Hoeger@billy.biz,"{""street"":""Rex Trail"",""suite"":""Suite 280"",""city"":""Howemouth"",""zipcode"":""58804-1099"",""geo"":{""lat"":""24.8918"",""lng"":""21.8984""}}",210.067.6132,"{""name"":""Johns Group"",""catchPhrase"":""Configurable multimedia task-force"",""bs"":""generate enterprise e-tailers""}",,,
8,"Nicholas Runolfsdottir V",Maxime_Nienow,Sherwood@rosamond.me,"{""street"":""Ellsworth Summit"",""suite"":""Suite 729"",""city"":""Aliyaview"",""zipcode"":""45169"",""geo"":{""lat"":""-14.3990"",""lng"":""-120.7677""}}","586.493.6943 x140","{""name"":""Abernathy Group"",""catchPhrase"":""Implemented secondary concept"",""bs"":""e-enable extensible e-tailers""}",,,
9,"Glenna Reichert",Delphine,Chaim_McDermott@dana.io,"{""street"":""Dayna Park"",""suite"":""Suite 449"",""city"":""Bartholomebury"",""zipcode"":""76495-3109"",""geo"":{""lat"":""24.6463"",""lng"":""-168.8889""}}","(775)976-6794 x41206","{""name"":""Yost and Sons"",""catchPhrase"":""Switchable contextually-based project"",""bs"":""aggregate real-time technologies""}",,,
10,"Clementina DuBuque",Moriah.Stanton,Rey.Padberg@karina.biz,"{""street"":""Kattie Turnpike"",""suite"":""Suite 198"",""city"":""Lebsackbury"",""zipcode"":""31428-2261"",""geo"":{""lat"":""-38.2386"",""lng"":""57.2232""}}",024-648-3804,"{""name"":""Hoeger LLC"",""catchPhrase"":""Centralized empowering task-force"",""bs"":""target end-to-end models""}",,,
✔ 10 entities retrieved without errors

EOF,
                0,
                ['--csv', 'user'],
                [
                    'http://localhost:3001/users' => 1,
                ],
            ],
            [
                <<<'EOF'
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User
id,name,postcode
1,"Leanne Graham",92998-3874
2,"Ervin Howell",90566-7771
3,"Clementine Bauch",59590-4157
4,"Patricia Lebsack",53919-4257
5,"Chelsey Dietrich",33263
6,"Mrs. Dennis Schulist",23505-1337
7,"Kurtis Weissnat",58804-1099
8,"Nicholas Runolfsdottir V",45169
9,"Glenna Reichert",76495-3109
10,"Clementina DuBuque",31428-2261
✔ 10 entities retrieved without errors

EOF,
                0,
                ['--shallow', '--field', 'id,name,address.zipcode=postcode', '--csv', 'user'],
                [
                    'http://localhost:3001/users' => 1,
                ],
            ],
            [
                <<<'EOF'
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User/7
id,name,username,email,address,phone,company,tasks,posts,albums
7,"Kurtis Weissnat",Elwyn.Skiles,Telly.Hoeger@billy.biz,"{""street"":""Rex Trail"",""suite"":""Suite 280"",""city"":""Howemouth"",""zipcode"":""58804-1099"",""geo"":{""lat"":""24.8918"",""lng"":""21.8984""}}",210.067.6132,"{""name"":""Johns Group"",""catchPhrase"":""Configurable multimedia task-force"",""bs"":""generate enterprise e-tailers""}",,,
✔ 1 entity retrieved without errors

EOF,
                0,
                ['--csv', 'user', '7'],
                [
                    'http://localhost:3001/users/7' => 1,
                ],
            ],
            [
                <<<'EOF'
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User
[
    {
        "id": 1,
        "name": "Leanne Graham"
    },
    {
        "id": 2,
        "name": "Ervin Howell"
    },
    {
        "id": 3,
        "name": "Clementine Bauch"
    },
    {
        "id": 4,
        "name": "Patricia Lebsack"
    },
    {
        "id": 5,
        "name": "Chelsey Dietrich"
    },
    {
        "id": 6,
        "name": "Mrs. Dennis Schulist"
    },
    {
        "id": 7,
        "name": "Kurtis Weissnat"
    },
    {
        "id": 8,
        "name": "Nicholas Runolfsdottir V"
    },
    {
        "id": 9,
        "name": "Glenna Reichert"
    },
    {
        "id": 10,
        "name": "Clementina DuBuque"
    }
]
✔ 10 entities retrieved without errors

EOF,
                0,
                ['--shallow', '--field', 'id,name', 'user'],
                [
                    'http://localhost:3001/users' => 1,
                ],
            ],
            [
                <<<'EOF'
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User
{
    "id": 1,
    "name": "Leanne Graham"
}
{
    "id": 2,
    "name": "Ervin Howell"
}
{
    "id": 3,
    "name": "Clementine Bauch"
}
{
    "id": 4,
    "name": "Patricia Lebsack"
}
{
    "id": 5,
    "name": "Chelsey Dietrich"
}
{
    "id": 6,
    "name": "Mrs. Dennis Schulist"
}
{
    "id": 7,
    "name": "Kurtis Weissnat"
}
{
    "id": 8,
    "name": "Nicholas Runolfsdottir V"
}
{
    "id": 9,
    "name": "Glenna Reichert"
}
{
    "id": 10,
    "name": "Clementina DuBuque"
}
✔ 10 entities retrieved without errors

EOF,
                0,
                ['--stream', '--shallow', '--field', 'id,name', 'user'],
                [
                    'http://localhost:3001/users' => 1,
                ],
            ],
            [
                <<<'EOF'
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User/7
{
    "id": 7,
    "name": "Kurtis Weissnat",
    "username": "Elwyn.Skiles",
    "email": "Telly.Hoeger@billy.biz",
    "address": {
        "street": "Rex Trail",
        "suite": "Suite 280",
        "city": "Howemouth",
        "zipcode": "58804-1099",
        "geo": {
            "lat": "24.8918",
            "lng": "21.8984"
        }
    },
    "phone": "210.067.6132",
    "company": {
        "name": "Johns Group",
        "catchPhrase": "Configurable multimedia task-force",
        "bs": "generate enterprise e-tailers"
    },
    "tasks": null,
    "posts": null,
    "albums": null
}
✔ 1 entity retrieved without errors

EOF,
                0,
                ['--shallow', 'user', '7'],
                [
                    'http://localhost:3001/users/7' => 1,
                ],
            ],
            [
                sprintf(<<<EOF
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User/-1
Error: entity not found: -1 (JSONPlaceholder { http://localhost:3001 } [#1] does not have %s with id = {-1})

app [-IMsc] [-p <provider>] [-f <term=value>,...] [--shallow]
    [-F (<field>|<field>=<title>),...] [--] <entity> [<entity-id>]

See 'app --help' for more information.

EOF, User::class),
                1,
                ['--', 'user', '-1'],
                [
                    'http://localhost:3001/users/-1' => 1,
                ],
            ],
            [
                <<<EOF
➤ Retrieving from JSONPlaceholder { http://localhost:3001 }: /Salient/Tests/Sync/Entity/User
Error: Invalid field: address.postcode

app [-IMsc] [-p <provider>] [-f <term=value>,...] [--shallow]
    [-F (<field>|<field>=<title>),...] [--] <entity> [<entity-id>]

See 'app --help' for more information.

EOF,
                1,
                ['--shallow', '--field', 'address.postcode', 'user'],
                [
                    'http://localhost:3001/users' => 1,
                ],
            ],
            [
                <<<EOF
NAME
    app - Get entities from a registered provider

SYNOPSIS
    app [-IMsc] [-p provider] [-f term=value,...] [--shallow]
        [-F (field|field=title),...] [--] entity [entity-id]

OPTIONS
    entity
        The entity can be:

        - album
        - collides
        - comment
        - photo
        - post
        - task
        - user

    entity-id
        The unique identifier of an entity to request

        If not given, a list of entities is requested.

    -p, --provider (json-placeholder-api)
        The provider to request entities from

        If not given, the entity's default provider is used.

    -f, --filter term=value,...
        Apply a filter to the request

    --shallow
        Do not resolve entity relationships

    -I, --include-canonical-id
        Include canonical_id in the output

    -M, --include-meta
        Include meta values in the output

    -s, --stream
        Output a stream of entities

    -F, --field (field|field=title),...
        Limit output to the given fields, e.g. "id,user.id=user id,title"

    -c, --csv
        Generate CSV output (implies --shallow if --field is not given)

EOF,
                0,
                ['--help'],
                [],
            ],
            [
                <<<EOF
NAME
    app - Get entities from a registered provider

SYNOPSIS
    app [-IMsc] [-p provider] [-f term=value,...] [--shallow]
        [-F (field|field=title),...] [--] entity [entity-id]

OPTIONS
    entity
        The entity can be:

        - album
        - comment
        - photo
        - post
        - task
        - user

    entity-id
        The unique identifier of an entity to request

        If not given, a list of entities is requested.

    -p, --provider provider
        The provider to request entities from

        If not given, the entity's default provider is used.

        The provider can be:

        - Salient\Tests\Sync\External\Provider\MockProvider
        - Salient\Tests\Sync\Provider\MockProvider
        - json-placeholder-api

    -f, --filter term=value,...
        Apply a filter to the request

    --shallow
        Do not resolve entity relationships

    -I, --include-canonical-id
        Include canonical_id in the output

    -M, --include-meta
        Include meta values in the output

    -s, --stream
        Output a stream of entities

    -F, --field (field|field=title),...
        Limit output to the given fields, e.g. "id,user.id=user id,title"

    -c, --csv
        Generate CSV output (implies --shallow if --field is not given)

EOF,
                0,
                ['--help'],
                [],
                1,
                [
                    stdClass::class,
                    MockProvider::class,
                    ExternalMockProvider::class,
                ],
            ],
            [
                <<<EOF
NAME
    app - Get entities from a provider

SYNOPSIS
    app [-IMsc] [-f term=value,...] [--shallow] [-F (field|field=title),...]
        -p provider [--] entity [entity-id]

OPTIONS
    entity
        The fully-qualified name of the entity to request

    entity-id
        The unique identifier of an entity to request

        If not given, a list of entities is requested.

    -p, --provider provider
        The fully-qualified name of the provider to request entities from

    -f, --filter term=value,...
        Apply a filter to the request

    --shallow
        Do not resolve entity relationships

    -I, --include-canonical-id
        Include canonical_id in the output

    -M, --include-meta
        Include meta values in the output

    -s, --stream
        Output a stream of entities

    -F, --field (field|field=title),...
        Limit output to the given fields, e.g. "id,user.id=user id,title"

    -c, --csv
        Generate CSV output (implies --shallow if --field is not given)

EOF,
                0,
                ['--help'],
                [],
                1,
                [],
                true,
            ],
            [
                sprintf(<<<EOF
Error: no default provider: %s

app [-IMsc] [-p <provider>] [-f <term=value>,...] [--shallow]
    [-F (<field>|<field>=<title>),...] [--] <entity> [<entity-id>]

See 'app --help' for more information.

EOF, Comment::class),
                1,
                ['comment'],
                [],
            ],
            [
                sprintf(<<<EOF
Error: stdClass does not implement %s

app [-IMsc] [-f <term=value>,...] [--shallow] [-F (<field>|<field>=<title>),...]
    -p <provider> [--] <entity> [<entity-id>]

See 'app --help' for more information.

EOF, SyncEntityInterface::class),
                1,
                ['--provider', JsonPlaceholderApi::class, stdClass::class],
                [],
                1,
                [],
                true,
            ],
            [
                sprintf(<<<EOF
Error: stdClass does not implement %s

app [-IMsc] [-f <term=value>,...] [--shallow] [-F (<field>|<field>=<title>),...]
    -p <provider> [--] <entity> [<entity-id>]

See 'app --help' for more information.

EOF, SyncProviderInterface::class),
                1,
                ['--provider', stdClass::class, Comment::class],
                [],
                1,
                [],
                true,
            ],
            [
                sprintf(<<<EOF
Error: %s does not service %s

app [-IMsc] [-f <term=value>,...] [--shallow] [-F (<field>|<field>=<title>),...]
    -p <provider> [--] <entity> [<entity-id>]

See 'app --help' for more information.

EOF, JsonPlaceholderApi::class, Unimplemented::class),
                1,
                ['--provider', JsonPlaceholderApi::class, Unimplemented::class],
                [],
                1,
                [],
                true,
            ],
            [
                sprintf(<<<EOF
Error: invalid filter (Invalid key-value pair: '=value')

app [-IMsc] [-p <provider>] [-f <term=value>,...] [--shallow]
    [-F (<field>|<field>=<title>),...] [--] <entity> [<entity-id>]

See 'app --help' for more information.

EOF),
                1,
                ['--filter', '=value', 'user'],
                [],
            ],
        ];
    }
}
