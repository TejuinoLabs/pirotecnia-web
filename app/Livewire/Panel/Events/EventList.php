<?php

namespace App\Livewire\Panel\Events;

use App\Models\Event;
use DateTime;
use DateTimeZone;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class EventList extends Component
{
    use WithPagination;

    public $selectedDate;

    public $todayDate;

    public function mount()
    {
        $this->todayDate = $this->getLocalToday();
        $this->selectedDate = $this->todayDate;
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.panel.events.event-list', ['events' => Event::with('package')->whereDate('event_date', $this->selectedDate)->orderBy('event_date')->orderBy('id')->paginate(15)]);
    }

    #[On('selectDate')]
    public function selectDate($date)
    {
        $this->selectedDate = $date;
        $this->resetPage();
    }

    public function showToday()
    {
        $this->selectedDate = $this->todayDate;
        $this->resetPage();
    }

    private function getLocalToday()
    {
        $utcDateTime = new DateTime('now', new DateTimeZone('UTC'));
        $utcDateTime->setTimezone(new DateTimeZone('America/Mexico_City'));

        return $utcDateTime->format('Y-m-d');
    }
}
