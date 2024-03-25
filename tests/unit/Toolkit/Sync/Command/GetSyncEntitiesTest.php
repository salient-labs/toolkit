<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Command;

use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Contract\Cli\CliCommandInterface;
use Salient\Sync\Command\GetSyncEntities;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\CommandTestCase;

/**
 * @covers \Salient\Sync\Command\GetSyncEntities
 */
final class GetSyncEntitiesTest extends CommandTestCase
{
    protected function startApp(CliApplicationInterface $app): CliApplicationInterface
    {
        return $app
            ->startCache()
            ->startSync(__METHOD__, [])
            ->provider(JsonPlaceholderApi::class);
    }

    protected function makeCommandAssertions(
        CliApplicationInterface $app,
        CliCommandInterface $command,
        ...$args
    ): void {
        $httpRequestCount = $args[8] ?? null;

        if ($httpRequestCount === null) {
            return;
        }

        $provider = $app->get(JsonPlaceholderApi::class);
        $this->assertSame(
            $httpRequestCount,
            $provider->HttpRequests,
            'JsonPlaceholderApi::$HttpRequestCount',
        );
    }

    /**
     * @dataProvider runProvider
     *
     * @param string[] $args
     * @param array<string,int>|null $httpRequestCount
     */
    public function testRun(
        string $output,
        int $exitStatus,
        array $args,
        ?array $httpRequestCount = null,
        int $runs = 1
    ): void {
        $this->assertCommandProduces(
            $output,
            $exitStatus,
            GetSyncEntities::class,
            $args,
            [],
            false,
            null,
            $runs,
            $httpRequestCount,
        );
    }

    /**
     * @return array<array{string,int,string[],3?:array<string,int>|null,4?:int}>
     */
    public static function runProvider(): array
    {
        return [
            [
                <<<'EOF'
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

                EOF,
                0,
                ['--csv', 'user'],
                [
                    'http://localhost:3001/users' => 1,
                ],
            ],
        ];
    }
}
