'use strict'

const { systemCmd } = require('./lib/system.js')

const plugins = ['ApiPreview']

;(async () => {
  try {
    await systemCmd('git submodule init')
    await systemCmd('git submodule update')
    await systemCmd('git submodule foreach npm ci')
    await systemCmd('git submodule foreach npm run setup')
    await systemCmd('npm ci')
    await Promise.all(
      plugins.map((plugin) =>
        systemCmd(`unlink ablogcms/extension/plugins/${plugin}`),
      ),
    )
    await Promise.all(
      plugins.map((plugin) =>
        systemCmd(
          `ln -s ../../../plugins/${plugin}/src ablogcms/extension/plugins/${plugin}`,
        ),
      ),
    )
  } catch (err) {
    console.log(err)
  }
})()
