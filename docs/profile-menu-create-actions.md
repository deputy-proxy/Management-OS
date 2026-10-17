# Profile-menu create actions

The admin panel currently exposes three record-creation actions in the profile menu. Each action reuses its resource's existing form schema so the modal and the resource create page share validation and relationship constraints.

| Menu action | Target model / resource | Required permission | Form source |
| --- | --- | --- | --- |
| Request Medical Leave (medical_leave) | EmployeeMedicalLeave / EmployeeMedicalLeaveResource | employee_medical_leafe.create | EmployeeMedicalLeaveResource::form() |
| Request Holiday (holiday) | EmployeeHoliday / EmployeeHolidayResource | employee_holiday.create | EmployeeHolidayResource::form() |
| Create a new task (task) | Task / TaskResource | task.create | TaskResource::form() |

## Authorization

Each action is hidden unless its corresponding HexaLite create permission is granted, and the same permission is checked when the action is authorized for execution. Hiding the menu item is not treated as the security boundary by itself.

The medical-leave and holiday forms constrain the employee selector according to the current employee's job title. The task form constrains assignment choices according to the existing task.assign and task.view_all permissions.

## Validation and save behavior

The actions use Filament's CreateAction with the existing resource form schemas. Required fields are validated by the schema, and Filament handles the create-action success notification and modal lifecycle. The task form marks the non-nullable description, due date, project, and project phase fields as required so the form catches missing values before the database write.

No additional profile-menu create actions are specified by issue #12 itself. Add further actions only when their target resource, permission, and intended audience are agreed.
