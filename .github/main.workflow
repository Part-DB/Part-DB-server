workflow "Build, Test, and Publish" {
  on = "push"
  resolves = ["Build"]
}

action "Build" {
  uses = "nuxt/actions-yarn@master"
  args = "install"
}

action "Compile Assets" {
  uses = "nuxt/actions-yarn@master"
  args = "encore production"
}
