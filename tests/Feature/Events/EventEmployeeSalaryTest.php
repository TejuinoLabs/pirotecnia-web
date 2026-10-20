<?php

namespace Tests\Feature\Events;

use App\Livewire\Panel\Events\EventForm;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class EventEmployeeSalaryTest extends TestCase
{
    use RefreshDatabase;

    private int $typeId;
    private Package $package;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->typeId = DB::table('event_types')->insertGetId(['name' => 'Especial', 'description' => 'Evento especial']);
        $this->package = Package::create(['name' => 'Show', 'description' => 'Show especial', 'price' => 1000]);
        $this->employee = new Employee;
        $this->employee->forceFill(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '3121234567', 'salary' => '500'])->save();
    }

    public function test_salary_override_is_saved_for_only_one_event_and_can_be_edited_or_cleared(): void
    {
        $this->newEventForm()
            ->set('employeeSalaries.'.$this->employee->id, '850.50')
            ->call('save')
            ->assertHasNoErrors();
        $event = Event::firstOrFail();
        $this->assertSame(850.5, (float) $event->employees->first()->pivot->salary);
        $this->assertSame('500', $this->employee->fresh()->salary);

        $this->newEventForm()->call('save')->assertHasNoErrors();
        $otherEvent = Event::latest('id')->firstOrFail();
        $this->assertNull($otherEvent->employees->first()->pivot->salary);

        Livewire::test(EventForm::class, ['event' => $event->fresh()])
            ->assertSet('employeeSalaries.'.$this->employee->id, '850.50')
            ->assertSee('Sueldo para este evento')
            ->set('employeeSalaries.'.$this->employee->id, '975')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(975.0, (float) $event->fresh()->employees->first()->pivot->salary);

        Livewire::test(EventForm::class, ['event' => $event->fresh()])
            ->set('employeeSalaries.'.$this->employee->id, '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull($event->fresh()->employees->first()->pivot->salary);
        $this->assertSame('500', $this->employee->fresh()->salary);
    }

    public function test_invalid_salary_is_rejected_before_saving(): void
    {
        foreach (['-1', 'abc', '12.345', '10000000000'] as $salary) {
            $this->newEventForm()
                ->set('employeeSalaries.'.$this->employee->id, $salary)
                ->call('save')
                ->assertHasErrors('employeeSalaries.'.$this->employee->id);
        }
        $this->assertDatabaseCount('events', 0);
    }

    public function test_salary_stays_with_the_employee_when_assignment_rows_change(): void
    {
        $other = new Employee;
        $other->forceFill(['name' => 'Luis', 'email' => 'luis@example.com', 'phone' => '3127654321', 'salary' => '600'])->save();

        $this->newEventForm()
            ->set('employeeSalaries.'.$this->employee->id, '850')
            ->set('employee_id', [$other->id])
            ->call('save')
            ->assertHasNoErrors();

        $assigned = Event::firstOrFail()->employees->sole();
        $this->assertSame($other->id, $assigned->id);
        $this->assertNull($assigned->pivot->salary);
    }

    public function test_zero_is_a_valid_override(): void
    {
        $this->newEventForm()
            ->set('employeeSalaries.'.$this->employee->id, '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0.0, (float) Event::firstOrFail()->employees->sole()->pivot->salary);
    }

    private function newEventForm()
    {
        return Livewire::test(EventForm::class)
            ->set('date', now()->format('Y-m-d'))
            ->set('phone', '3121234567')
            ->set('client_name', 'Cliente')
            ->set('event_address', 'Salón')
            ->set('event_date', now()->addMonth()->format('Y-m-d'))
            ->set('event_time', '20:00')
            ->set('event_type_id', $this->typeId)
            ->set('packageMode', 'registered')
            ->set('package_id', [$this->package->id])
            ->set('employee_id', [$this->employee->id]);
    }
}
