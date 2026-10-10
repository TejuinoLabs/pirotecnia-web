<?php

namespace App\Http\Controllers\API;

use App\Core\Data\Entities\Event;
use App\Core\UseCases\Events\CreateEvent;
use App\Core\UseCases\Events\GetAllEvents;
use App\Core\UseCases\Events\GetEvent;
use App\Core\UseCases\Events\GetEventsByEmployee;
use App\Helper\PdfQuoteFiller;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    //

    public function index(Request $request)
    {
        $events = app(GetAllEvents::class)->execute($request->get('page', 1));

        return response()->success($events, 200);
    }

    public function getEventsByEmployee(Request $request)
    {
        abort_unless($request->user()->employee, 403, 'No tienes un empleado asociado.');
        $id = $request->user()->employee->id;
        $events = app(GetEventsByEmployee::class)->execute($id, $request->get('page', 1));

        return response()->success($events, 200);
    }

    public function show(Request $request, int $id)
    {
        $actor = $request->user();
        $roles = $actor->roles()->pluck('name')->merge([$actor->role?->name]);
        if ($roles->intersect(['root', 'admin'])->isEmpty()) {
            if ($roles->contains('technician') || $actor->employee) {
                app(\App\Services\Mobile\TechnicianEventService::class)->authorize($actor, $id);
            } else {
                app(\App\Services\Inventory\InventoryAccess::class)->authorize($actor, 'events.view');
            }
        }
        \App\Services\Inventory\InventoryData::get('events', $id);
        $event = app(GetEvent::class)->execute($id);
        abort_unless($event, 404);
        $pdf = $this->generatePdf($event);
        $fileName = 'event_'.$event->id.'.pdf';
        Storage::disk('public')->put('pdf/'.$fileName, $pdf);
        $url = asset('storage/pdf/'.$fileName);
        $event->pdf_url = $url;

        return response()->success($event, 200);
    }

    private function generatePdf($event)
    {
        if ($event) {
            $price = (float) $event->price > 0 ? (float) $event->price : (float) $event->packages->sum('price');
            $event->full_price = $price;
            $event->package = $event->packages->pluck('name')->implode(', ');
            if ($event->package === '' && $event->products->isNotEmpty()) {
                $event->package = 'Paquete personalizado';
            }
            if ($event->discount > 1) {
                $event->balance = ($price - $event->discount) - $event->advance + $event->travel_expenses;
            } else {
                $event->balance = ($price - ($price * $event->discount)) - $event->advance + $event->travel_expenses;
            }
        }

        $pdf = Pdf::loadView('whatsapp.event_details_pdf_view_api', compact('event'));

        return $pdf->output();
    }

    public function store(StoreEventRequest $request)
    {
        $event = app(CreateEvent::class)->execute(Event::fromArray($request->all()));

        return $event;
    }

    private function generateContrato($data)
    {
        $package_names = implode(', ', $data->packages->pluck('name')->toArray());
        if ($package_names === '' && $data->products->isNotEmpty()) {
            $package_names = 'Paquete personalizado';
        }

        $items = $data->products->map(fn ($p) => [
            'descripcion' => $p->name,
            'cantidad' => $p->quantity,
            'precio' => $p->price,
        ])->toArray();

        $price = 0;
        foreach ($data->packages as $package) {
            $price += $package->price;
        }
        $data = [
            'fecha' => $data->date,
            'telefono' => $data->phone,
            'nombre' => $data->client_name,
            'domicilio' => $data->client_address,
            'lugar_evento' => $data->event_address,
            'fecha_hora_evento' => $data->event_date,
            'tipo_evento' => $data->event_type,
            'anticipo' => $data->advance,
            'saldo' => $price - $data->discount + $data->travel_expenses,
            'paquete' => $package_names,
            'items' => $items,
            'viaticos' => $data->travel_expenses,
            'packages' => $data->packages,
            'discount' => $data->discount,
            'total' => $price + $data->travel_expenses,
        ];
        $pdf = new PdfQuoteFiller;

        return $pdf->fill($data);
    }

    public function update(Request $request, $id) {}

    public function destroy(Request $request, $id) {}
}
