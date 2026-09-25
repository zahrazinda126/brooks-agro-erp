<?php

namespace Tests\Feature;

use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProductionBatchCostsTest extends TestCase
{
    public function test_production_can_omit_finance_fields_and_cannot_submit_changes_to_them(): void
    {
        $user = new User;
        $user->setRelation('role', new Role(['code' => 'production-manager']));
        $user->setRelation('roles', collect());

        foreach ([StoreBatchRequest::class, UpdateBatchRequest::class] as $requestClass) {
            $request = new $requestClass;
            $request->setUserResolver(fn () => $user);
            $rules = array_intersect_key($request->rules(), array_flip(['unit_cost', 'total_cost', 'supplier_name', 'invoice_no', 'notes']));

            foreach ([[], ['unit_cost' => 900, 'total_cost' => 90000, 'supplier_name' => 'Changed supplier', 'invoice_no' => 'Changed invoice', 'notes' => 'Changed finance notes']] as $input) {
                $validator = Validator::make($input, $rules);
                $this->assertTrue($validator->passes());
                $this->assertSame([], $validator->validated());
            }
        }
    }
}
