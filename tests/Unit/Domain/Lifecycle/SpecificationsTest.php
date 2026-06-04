<?php

use App\Domain\Lifecycle\Specification\RoleSpecification;
use App\Domain\Lifecycle\Specification\SeparationOfDutySpecification;
use App\Domain\Lifecycle\Specification\TenantOwnershipSpecification;

test('tenant ownership specification passes when actor and resource belong same tenant', function () {
    $specification = new TenantOwnershipSpecification();

    expect($specification->isSatisfiedBy([
        'actor_tenant_id' => 3,
        'resource_tenant_id' => 3,
    ]))->toBeTrue();
});

test('tenant ownership specification fails when tenant mismatch', function () {
    $specification = new TenantOwnershipSpecification();

    expect($specification->isSatisfiedBy([
        'actor_tenant_id' => 3,
        'resource_tenant_id' => 5,
    ]))->toBeFalse()
        ->and($specification->message())->toBe('Dữ liệu không thuộc tenant hiện tại.');
});

test('role specification passes when at least one required role exists', function () {
    $specification = new RoleSpecification(['manager', 'accountant']);

    expect($specification->isSatisfiedBy([
        'roles' => ['staff', 'Accountant'],
    ]))->toBeTrue();
});

test('role specification fails when no role matches', function () {
    $specification = new RoleSpecification(['manager', 'accountant']);

    expect($specification->isSatisfiedBy([
        'roles' => ['staff', 'cashier'],
    ]))->toBeFalse()
        ->and($specification->message())->toBe('Không có vai trò phù hợp để thực hiện thao tác.');
});

test('role specification require-all mode validates all roles', function () {
    $specification = new RoleSpecification(['manager', 'admin'], true);

    expect($specification->isSatisfiedBy([
        'roles' => ['manager', 'admin'],
    ]))->toBeTrue();

    expect($specification->isSatisfiedBy([
        'roles' => ['manager'],
    ]))->toBeFalse();
});

test('separation of duty specification passes when actor differs from creator', function () {
    $specification = new SeparationOfDutySpecification();

    expect($specification->isSatisfiedBy([
        'actor_id' => 10,
        'creator_id' => 11,
    ]))->toBeTrue();
});

test('separation of duty specification fails when actor equals creator', function () {
    $specification = new SeparationOfDutySpecification();

    expect($specification->isSatisfiedBy([
        'actor_id' => 10,
        'creator_id' => 10,
    ]))->toBeFalse()
        ->and($specification->message())->toBe('Người tạo không được tự duyệt/chốt chứng từ (SoD).');
});
