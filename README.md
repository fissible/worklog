# worklog
A Command Line application for logging daily work items

```
wlog feature <name>
    wlog vcr branch <name> feature
        switched to a new branch 'development-feature-test'

wlog feature
    wlog vcr commit -p
    wlog vcr merge
    wlog vcr close

wlog hotfix <name>
    wlog vcr branch <name> hotifx

wlog hotfix
    wlog vcr commit -p
    wlog vcr merge
    wlog vcr close

wlog release <name>
    wlog vcr branch <name> release

wlog release
    wlog vcr commit -p
    wlog version increment
        > 4.0.0
        > Release new feature
    wlog vcr merge
    wlog vcr close
```