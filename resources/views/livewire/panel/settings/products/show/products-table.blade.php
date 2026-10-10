<x-card title="Productos" icon="fas fa-box">
    <x-slot:tools>
        <button class="btn btn-primary btn-sm" wire:click='switchToAddProductMode'>
            <i class="fas fa-plus"></i>
        </button>
    </x-slot>
    <x-slot:body class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>

                    <th class="col-4">Descripción</th>
                    <th class="col-1">Cantidad</th>
                    <th class="col-1">Unidad</th>
                    <th class="col-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($components as $idnex => $productAux)
                    <tr wire:key="{{ $productAux->id }}">
                        <td>
                            {{ $productAux->name }}

                        </td>
                        <td>
                            @if ($this->isEditMode && $this->productId == $productAux->id)
                                <input type="text" wire:model="quantity" class="form-control">
                            @else
                                {{ $productAux->pivot->quantity }}
                            @endif
                        </td>
                        <td>
                            {{ $productAux->unit }}

                        </td>
                        <td>
                            @if ($this->isEditMode && $this->productId == $productAux->id)
                                <button class="btn btn-success btn-sm" wire:click="editMaterialInPackage({{ $productAux->id }})">
                                    <i class="fas fa-save"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" wire:click="cancelEditMaterial">
                                    <i class="fas fa-window-close"></i>
                                </button>
                            @else
                                <form wire:submit='removeMaterialFromPackage({{ $productAux->id }})' class="d-inline">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if ($this->isAddProduct)
                    <tr>
                        <td>
                            <select wire:model.live="materialId" class="form-control">
                                <option value="">Selecciona un material</option>
                                @foreach ($materials as $material)
                                    <option value="{{ $material->id }}">{{ $material->name }}</option>
                                @endforeach
                                
                            </select>
                        </td>
                        <td>
                            
                        </td>
                        <td>

                        </td>
                        <td>
                            <button class="btn btn-success btn-sm" wire:click="addMaterialToProduct">
                                <i class="fas fa-save"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" wire:click="cancelAddMaterial">
                                <i class="fas fa-window-close"></i>
                            </button>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
        <div class="p-4">{{ $components->links() }}</div>
    </x-slot>
</x-card>