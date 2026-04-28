# Wayfinder Core

Wayfinder is the reusable core framework package behind Stackmint.

It provides the runtime pieces that Stackmint builds on: routing, HTTP, minimal DI, configuration, views, sessions, database access, console tooling, modules, and testing support.

## Philosophy

Wayfinder is designed around:

- explicit wiring
- low magic
- model-first application code
- AI-readable structure
- a small dependency surface

It intentionally does not include:

- an ORM
- a queue system
- a large hidden abstraction layer

## Mental Model

- `Model` = entity behavior
- `Query` = complex read shape
- `Service` = workflow
- `DB` = low-level control
- `DTO` = explicit output shape

## Use Wayfinder Through Stackmint

Most developers should start from the Stackmint starter rather than consuming the core package in isolation.

- Stackmint starter: <https://github.com/trafficinc/stackmint>
- Full docs: <https://github.com/trafficinc/stackmint-docs>

## Package

- Composer package: `wayfinder/core`
- Namespace root: `Wayfinder\\`
