<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Events\Event;
use Volt\Core\Events\EventBus;

/**
 * Integration tests cho VoltModel Event Bus dispatch.
 *
 * @internal
 */
final class VoltModelEventTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private EventBus $eventBus;

    /** @var list<Event> */
    private array $dispatched = [];

    private UserEntity $testActor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventBus = new EventBus();
        $this->dispatched = [];

        $this->eventBus->listen('*', function (Event $e): void {
            $this->dispatched[] = $e;
        });

        $db = VoltDatabase::connection();

        $this->testActor = new UserEntity();
        $this->testActor->name = 'admin';
        $this->testActor->roles = ['admin'];

        $exists = $db->table('sys_entity')->where('name', 'test_evt')->get()->getRowArray();
        if (! is_array($exists)) {
            $db->table('sys_entity')->insert([
                'name'              => 'test_evt',
                'module'            => 'core',
                'autoname'          => 'HASH',
                'issingle'          => 0,
                'istable'           => 0,
                'custom_attributes' => json_encode(['is_submittable' => true, 'label' => 'Test Evt']),
            ]);
        }

        $tableName = 'tab_test_evt';
        $db->query("DROP TABLE IF EXISTS {$tableName}");
        $db->query("
            CREATE TABLE {$tableName} (
                name VARCHAR(100) PRIMARY KEY,
                docstatus SMALLINT DEFAULT 0,
                workflow_state VARCHAR(100) DEFAULT 'Draft',
                owner VARCHAR(100) DEFAULT 'test',
                amended_from VARCHAR(100) DEFAULT NULL,
                creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    protected function tearDown(): void
    {
        $db = VoltDatabase::connection();
        $db->query('DROP TABLE IF EXISTS tab_test_evt');
        $db->table('sys_entity')->where('name', 'test_evt')->delete();

        parent::tearDown();
    }

    public function testInsertDispatchesCreatedEvent(): void
    {
        $model = $this->createModel();
        $model->insert(['name' => 'EVT-001']);

        $this->assertCount(1, $this->dispatched);

        $event = $this->dispatched[0];
        $this->assertSame('volt.model.created', $event->getName());
        $this->assertSame('test_evt', $event->get('entity'));
        $this->assertSame('EVT-001', $event->get('id'));
    }

    public function testUpdateDispatchesUpdatedEvent(): void
    {
        $model = $this->createModel();
        $model->insert(['name' => 'EVT-002']);
        $this->dispatched = [];

        $model->update('EVT-002', ['owner' => 'new_owner']);

        $this->assertCount(1, $this->dispatched);

        $event = $this->dispatched[0];
        $this->assertSame('volt.model.updated', $event->getName());
        $this->assertSame('test_evt', $event->get('entity'));
        $this->assertSame('EVT-002', $event->get('id'));
    }

    public function testDeleteDispatchesDeletedEvent(): void
    {
        $model = $this->createModel();
        $model->insert(['name' => 'EVT-003']);
        $this->dispatched = [];

        $model->delete('EVT-003');

        $this->assertCount(1, $this->dispatched);

        $event = $this->dispatched[0];
        $this->assertSame('volt.model.deleted', $event->getName());
        $this->assertSame('test_evt', $event->get('entity'));
        $this->assertSame('EVT-003', $event->get('id'));
    }

    public function testSubmitDispatchesSubmittedEvent(): void
    {
        $model = $this->createModel();
        $model->insert(['name' => 'EVT-004']);
        $this->dispatched = [];

        $result = $model->submit('EVT-004', 'submitting');

        $events = $this->filterEvents('volt.model.submitted');
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertSame('test_evt', $event->get('entity'));
        $this->assertSame('EVT-004', $event->get('id'));
        $this->assertSame('submitting', $event->get('comment'));
        $this->assertSame($result, $event->get('result'));
    }

    public function testApproveDispatchesApprovedEvent(): void
    {
        $model = $this->createModel();
        $model->insert(['name' => 'EVT-005']);
        $model->submit('EVT-005');
        $this->dispatched = [];

        $result = $model->approve('EVT-005', 'approved');

        $events = $this->filterEvents('volt.model.approved');
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertSame('test_evt', $event->get('entity'));
        $this->assertSame('EVT-005', $event->get('id'));
        $this->assertSame('approved', $event->get('comment'));
        $this->assertSame($result, $event->get('result'));
    }

    public function testCancelDispatchesCancelledEvent(): void
    {
        $model = $this->createModel();
        $model->insert(['name' => 'EVT-006']);
        $model->submit('EVT-006');
        $this->dispatched = [];

        $result = $model->cancel('EVT-006', 'cancelling');

        $events = $this->filterEvents('volt.model.cancelled');
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertSame('test_evt', $event->get('entity'));
        $this->assertSame('EVT-006', $event->get('id'));
        $this->assertSame('cancelling', $event->get('comment'));
        $this->assertSame($result, $event->get('result'));
    }

    public function testAmendDispatchesAmendedEvent(): void
    {
        $model = $this->createModel();
        $model->insert(['name' => 'EVT-007']);
        $model->submit('EVT-007');
        $model->cancel('EVT-007');
        $this->dispatched = [];

        $amended = $model->amend('EVT-007');

        $events = $this->filterEvents('volt.model.amended');
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertSame('test_evt', $event->get('entity'));
        $this->assertSame('EVT-007', $event->get('old_id'));
        $this->assertIsString($event->get('new_id'));
        $this->assertNotSame('EVT-007', $event->get('new_id'));
        $this->assertSame($amended, $event->get('record'));
    }

    /** @return list<Event> */
    private function filterEvents(string $name): array
    {
        return array_values(
            array_filter($this->dispatched, static fn (Event $e): bool => $e->getName() === $name),
        );
    }

    private function createModel(): Volt\Core\Models\VoltModel
    {
        $model = new class ($this->eventBus) extends Volt\Core\Models\VoltModel {
            protected $table = 'tab_test_evt';
            protected $primaryKey = 'name';
            protected $returnType = 'array';
            protected $useAutoIncrement = false;
            protected $protectFields = false;
            protected $allowedFields = [];
            private EventBus $testBus;

            public function __construct(EventBus $bus)
            {
                $this->testBus = $bus;
                parent::__construct();
                $this->setEntityName('test_evt');
            }

            protected function eventBus(): EventBus
            {
                return $this->testBus;
            }
        };

        $model->setActor($this->testActor);

        return $model;
    }
}
