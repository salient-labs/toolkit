# Service containers

## `ContainerInterface`

If a service resolves to a new instance of a class that implements
`ContainerAwareInterface`, the container is passed to its
`ContainerAwareInterface::setContainer()` method.

Then, if the resolved instance implements `ServiceAwareInterface`, its
`ServiceAwareInterface::setService()` method is called.

A service provider registered via `ContainerInterface::provider()` or
`ContainerInterface::providers()` may also implement any combination of the
following interfaces:

- `SingletonInterface` to be instantiated once per container
- `HasServices` to specify which of its interfaces are services to register with
  the container
- `HasBindings` to bind additional services to the container
- `HasContextualBindings` to bind services to the container that only apply in
  the context of the provider

`SingletonInterface` is ignored if a lifetime other than
`ServiceLifetime::INHERIT` is given when the service provider is registered.
