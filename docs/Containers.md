## Services

Consider the following scenario:

- `Faculty` is a `SyncEntity` subclass and therefore implements `IProvidable`
- `CustomFaculty` is a subclass of `Faculty`
- `CustomFaculty` is bound to the service container as `Faculty`:
  ```php
  $this->App->bind(Faculty::class, CustomFaculty::class);
  ```
- `$provider` implements `FacultyProvider`
- A `Faculty` object is requested from `$provider` for faculty #1:
  ```php
  $faculty = $provider->with(Faculty::class)->get(1);
  ```

`$faculty` is now a `Faculty` service and an instance of `CustomFaculty`, so
this code:

```php
print_r([
    'class'   => get_class($faculty),
    'service' => $faculty->service(),
]);
```

will produce the following output:

```
Array
(
    [class] => CustomFaculty
    [service] => Faculty
)
```

