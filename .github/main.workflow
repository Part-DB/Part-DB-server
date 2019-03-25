workflow "Build, Test, and Publish" {
  on = "push"
  resolves = [
    "Install Composer dependencies",
    "Build frontent code.",
    "Install frontend depencies.",
  ]
}

action "Install frontend depencies." {
  uses = "nuxt/actions-yarn@master"
  args = "install"
}

action "Compile Assets" {
  uses = "nuxt/actions-yarn@master"
  args = "encore production"
}

action "Build frontent code." {
  uses = "nuxt/actions-yarn@master"
  args = "run build"
  needs = ["Install frontend depencies."]
}

action "Install Composer dependencies" {
  uses = "pxgamer/composer-action@master"
  args = "install -a"
}
