'use strict'

const { systemCmd } = require('./lib/system.js')
const fs = require('fs')
const path = require('path')

// Automatically discover plugins from plugins directory
const pluginsDir = path.join(__dirname, '..', 'plugins')
const plugins = fs
  .readdirSync(pluginsDir)
  .filter((item) => fs.statSync(path.join(pluginsDir, item)).isDirectory())
  .filter((item) => !item.startsWith('.'))

;(async () => {
  try {
    await systemCmd('git submodule init')
    await systemCmd('git submodule update')
    await systemCmd('git submodule foreach npm ci')
    await systemCmd('git submodule foreach npm run setup')
    await systemCmd('npm ci')
    await Promise.all(
      plugins.map((plugin) => {
        if (fs.existsSync(`ablogcms/extension/plugins/${plugin}`)) {
          return systemCmd(`unlink ablogcms/extension/plugins/${plugin}`)
        }
        return Promise.resolve()
      }),
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
