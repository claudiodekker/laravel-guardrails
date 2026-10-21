# Publish a check pack

Any Composer package can provide checks and presets. Declare them under `extra.guardrails.provides`, and require this package so the `Check` class is there:

```json
{
    "name": "acme/guardrails-pack",
    "description": "The guardrails of Acme's Laravel apps.",
    "type": "library",
    "require": {
        "php": "^8.3",
        "claudiodekker/laravel-guardrails": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Guardrails\\": "src/"
        }
    },
    "extra": {
        "guardrails": {
            "provides": {
                "checks": [
                    "Acme\\Guardrails\\NoDateFunctions",
                    "Acme\\Guardrails\\NoRawQueries"
                ],
                "presets": {
                    "acme": [
                        "ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\OneClassPerFile",
                        "Acme\\Guardrails\\NoDateFunctions",
                        "Acme\\Guardrails\\NoRawQueries"
                    ]
                },
                "descriptions": {
                    "acme": "The rules of the Acme team."
                }
            }
        }
    }
}
```

`checks` lists the classes the pack provides. `presets` maps each preset name to the classes it turns on, which may include checks of other packages. `descriptions` maps a preset name to the line the picker and `guardrails list` show beside it, and may leave a preset out.

Once an app requires the pack, `guardrails install` and `guardrails list` show its checks under `acme/guardrails-pack`, in a group per namespace below the one the checks share. The preset shows as `acme (acme/guardrails-pack)`. When another package names a preset alike, `--preset=acme/guardrails-pack:acme` names it in full.
