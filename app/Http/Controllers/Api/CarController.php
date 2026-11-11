<?php

namespace App\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CarRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CarController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = Car::query()
            ->when($request->user()->role === UserRole::Instructor, fn (Builder $query) => $query->where('is_active', true))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->validated('is_active')))
            ->when($request->validated('category'), fn (Builder $query, $category) => $query->where('category', $category))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->validated('search');
                $query->where(fn (Builder $fields) => $fields->where('brand', 'like', "%$search%")
                    ->orWhere('model', 'like', "%$search%")->orWhere('plate_number', 'like', "%$search%"));
            });

        return CarResource::collection($request->paginate($query));
    }

    public function show(Request $request, Car $car): CarResource
    {
        abort_unless($request->user()->role === UserRole::Admin || $car->is_active, 403);

        return new CarResource($car);
    }

    public function store(CarRequest $request): CarResource
    {
        return new CarResource(Car::create($request->validated())->refresh());
    }

    public function update(CarRequest $request, Car $car): CarResource
    {
        $updated = DB::transaction(function () use ($request, $car) {
            $car = Car::query()->lockForUpdate()->findOrFail($car->id);
            $data = $request->validated();

            if (isset($data['category']) && $data['category'] !== $car->category->value && $car->lessons()->exists()) {
                throw new BusinessException('Категорию транспорта с занятиями изменить нельзя.');
            }
            if (isset($data['is_active']) && ! $data['is_active'] && $car->lessons()
                ->where('status', '!=', LessonStatus::Cancelled->value)->where('end_at', '>', now())->exists()) {
                throw new BusinessException('Сначала отмените предстоящие занятия на этом транспорте.');
            }

            $car->update($data);

            return $car;
        }, 5);

        return new CarResource($updated);
    }

    public function destroy(Car $car): Response
    {
        DB::transaction(function () use ($car) {
            $car = Car::query()->lockForUpdate()->findOrFail($car->id);
            if ($car->lessons()->exists()) {
                throw new BusinessException('Транспорт с занятиями нельзя удалить. Его можно отключить.');
            }
            $car->delete();
        }, 5);

        return response()->noContent();
    }
}
