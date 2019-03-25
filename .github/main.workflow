workflow "Build, Test, and Publish" {
  on = "push"
  resolves = ["new-action"]
}

action "Build" {
  uses = "nuxt/actions-yarn@master"
  args = "install"
}

action "Compile Assets" {
  uses = "nuxt/actions-yarn@master"
  args = "encore production"
}

action "nuxt/actions-yarn@master" {
  uses = "nuxt/actions-yarn@master"
  needs = ["Build"]
  args = "run"
}

action "new-action" {
  uses = "owner/repo/path@ref"
  needs = ["nuxt/actions-yarn@master"]
}
