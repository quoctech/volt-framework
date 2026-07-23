<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Volt\Core\Engine\WorkflowEngine;

/**
 * @internal
 */
final class WorkflowEngineTest extends CIUnitTestCase
{
    private WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new WorkflowEngine();
    }

    public function testGetImplicitWorkflowReturnsFourStates(): void
    {
        $wf = $this->engine->getImplicitWorkflow('TestEntity');

        $this->assertSame('implicit', $wf['name']);
        $this->assertCount(4, $wf['states']);
        $this->assertSame('Draft', $wf['states'][0]['name']);
        $this->assertSame('Submitted', $wf['states'][1]['name']);
        $this->assertSame('Approved', $wf['states'][2]['name']);
        $this->assertSame('Cancelled', $wf['states'][3]['name']);
    }

    public function testGetImplicitWorkflowHasAllTransitions(): void
    {
        $wf = $this->engine->getImplicitWorkflow('TestEntity');

        $this->assertCount(4, $wf['transitions']);
    }

    public function testCanTransitionImplicitDraftToSubmitted(): void
    {
        $this->assertTrue($this->engine->canTransition('implicit', 'Draft', 'submit'));
    }

    public function testCanTransitionImplicitSubmittedToApprove(): void
    {
        $this->assertTrue($this->engine->canTransition('implicit', 'Submitted', 'approve'));
    }

    public function testCanTransitionImplicitSubmittedToCancel(): void
    {
        $this->assertTrue($this->engine->canTransition('implicit', 'Submitted', 'cancel'));
    }

    public function testCanTransitionImplicitCancelledToAmend(): void
    {
        $this->assertTrue($this->engine->canTransition('implicit', 'Cancelled', 'amend'));
    }

    public function testCannotTransitionInvalidImplicit(): void
    {
        $this->assertFalse($this->engine->canTransition('implicit', 'Draft', 'approve'));
        $this->assertFalse($this->engine->canTransition('implicit', 'Approved', 'cancel'));
        $this->assertFalse($this->engine->canTransition('implicit', 'Cancelled', 'submit'));
    }

    public function testIsSubmittableReturnsFalseForUnknownEntity(): void
    {
        $this->assertFalse($this->engine->isSubmittable('NonExistentEntityXYZ'));
    }
}
