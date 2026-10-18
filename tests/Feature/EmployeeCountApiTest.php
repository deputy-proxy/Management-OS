<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmployeeCountApiTest extends TestCase
{
    public function test_employee_count_endpoint_is_not_available_without_an_approved_consumer(): void
    {
        $this->getJson('/api/employees/count')->assertNotFound();
    }

    public function test_sanctum_user_endpoint_remains_protected(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
