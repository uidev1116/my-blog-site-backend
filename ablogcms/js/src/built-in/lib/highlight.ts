import hljs from 'highlight.js/lib/core';
import { HLJSApi, Language } from 'highlight.js';

type LanguageModule = () => Promise<{ default: (hljs: HLJSApi) => Language }>;

const loadLanguageModule = async (hljs: HLJSApi, includeLanguages: string[]): Promise<void> => {
  const availableLanguageModules: Record<string, LanguageModule> = {
    '1c': () => import(/* webpackChunkName: "highlight.js/languages/1c" */ 'highlight.js/lib/languages/1c'),
    abnf: () => import(/* webpackChunkName: "highlight.js/languages/abnf" */ 'highlight.js/lib/languages/abnf'),
    actionscript: () =>
      import(/* webpackChunkName: "highlight.js/languages/actionscript" */ 'highlight.js/lib/languages/actionscript'),
    ada: () => import(/* webpackChunkName: "highlight.js/languages/ada" */ 'highlight.js/lib/languages/ada'),
    apache: () => import(/* webpackChunkName: "highlight.js/languages/apache" */ 'highlight.js/lib/languages/apache'),
    arcade: () => import(/* webpackChunkName: "highlight.js/languages/arcade" */ 'highlight.js/lib/languages/arcade'),
    armasm: () => import(/* webpackChunkName: "highlight.js/languages/armasm" */ 'highlight.js/lib/languages/armasm'),
    asciidoc: () =>
      import(/* webpackChunkName: "highlight.js/languages/asciidoc" */ 'highlight.js/lib/languages/asciidoc'),
    autohotkey: () =>
      import(/* webpackChunkName: "highlight.js/languages/autohotkey" */ 'highlight.js/lib/languages/autohotkey'),
    autoit: () => import(/* webpackChunkName: "highlight.js/languages/autoit" */ 'highlight.js/lib/languages/autoit'),
    avrasm: () => import(/* webpackChunkName: "highlight.js/languages/avrasm" */ 'highlight.js/lib/languages/avrasm'),
    awk: () => import(/* webpackChunkName: "highlight.js/languages/awk" */ 'highlight.js/lib/languages/awk'),
    bash: () => import(/* webpackChunkName: "highlight.js/languages/bash" */ 'highlight.js/lib/languages/bash'),
    brainfuck: () =>
      import(/* webpackChunkName: "highlight.js/languages/brainfuck" */ 'highlight.js/lib/languages/brainfuck'),
    c: () => import(/* webpackChunkName: "highlight.js/languages/c" */ 'highlight.js/lib/languages/c'),
    cal: () => import(/* webpackChunkName: "highlight.js/languages/cal" */ 'highlight.js/lib/languages/cal'),
    capnproto: () =>
      import(/* webpackChunkName: "highlight.js/languages/capnproto" */ 'highlight.js/lib/languages/capnproto'),
    clojure: () =>
      import(/* webpackChunkName: "highlight.js/languages/clojure" */ 'highlight.js/lib/languages/clojure'),
    'clojure-repl': () =>
      import(/* webpackChunkName: "highlight.js/languages/clojure-repl" */ 'highlight.js/lib/languages/clojure-repl'),
    clean: () => import(/* webpackChunkName: "highlight.js/languages/clean" */ 'highlight.js/lib/languages/clean'),
    cmake: () => import(/* webpackChunkName: "highlight.js/languages/cmake" */ 'highlight.js/lib/languages/cmake'),
    coffeescript: () =>
      import(/* webpackChunkName: "highlight.js/languages/coffeescript" */ 'highlight.js/lib/languages/coffeescript'),
    cos: () => import(/* webpackChunkName: "highlight.js/languages/cos" */ 'highlight.js/lib/languages/cos'),
    cpp: () => import(/* webpackChunkName: "highlight.js/languages/cpp" */ 'highlight.js/lib/languages/cpp'),
    crmsh: () => import(/* webpackChunkName: "highlight.js/languages/crmsh" */ 'highlight.js/lib/languages/crmsh'),
    csharp: () => import(/* webpackChunkName: "highlight.js/languages/csharp" */ 'highlight.js/lib/languages/csharp'),
    css: () => import(/* webpackChunkName: "highlight.js/languages/css" */ 'highlight.js/lib/languages/css'),
    dart: () => import(/* webpackChunkName: "highlight.js/languages/dart" */ 'highlight.js/lib/languages/dart'),
    diff: () => import(/* webpackChunkName: "highlight.js/languages/diff" */ 'highlight.js/lib/languages/diff'),
    dns: () => import(/* webpackChunkName: "highlight.js/languages/dns" */ 'highlight.js/lib/languages/dns'),
    dockerfile: () =>
      import(/* webpackChunkName: "highlight.js/languages/dockerfile" */ 'highlight.js/lib/languages/dockerfile'),
    dos: () => import(/* webpackChunkName: "highlight.js/languages/dos" */ 'highlight.js/lib/languages/dos'),
    dsconfig: () =>
      import(/* webpackChunkName: "highlight.js/languages/dsconfig" */ 'highlight.js/lib/languages/dsconfig'),
    dts: () => import(/* webpackChunkName: "highlight.js/languages/dts" */ 'highlight.js/lib/languages/dts'),
    ebnf: () => import(/* webpackChunkName: "highlight.js/languages/ebnf" */ 'highlight.js/lib/languages/ebnf'),
    elixir: () => import(/* webpackChunkName: "highlight.js/languages/elixir" */ 'highlight.js/lib/languages/elixir'),
    elm: () => import(/* webpackChunkName: "highlight.js/languages/elm" */ 'highlight.js/lib/languages/elm'),
    erlang: () => import(/* webpackChunkName: "highlight.js/languages/erlang" */ 'highlight.js/lib/languages/erlang'),
    'erlang-repl': () =>
      import(/* webpackChunkName: "highlight.js/languages/erlang-repl" */ 'highlight.js/lib/languages/erlang-repl'),
    excel: () => import(/* webpackChunkName: "highlight.js/languages/excel" */ 'highlight.js/lib/languages/excel'),
    fix: () => import(/* webpackChunkName: "highlight.js/languages/fix" */ 'highlight.js/lib/languages/fix'),
    flix: () => import(/* webpackChunkName: "highlight.js/languages/flix" */ 'highlight.js/lib/languages/flix'),
    fortran: () =>
      import(/* webpackChunkName: "highlight.js/languages/fortran" */ 'highlight.js/lib/languages/fortran'),
    fsharp: () => import(/* webpackChunkName: "highlight.js/languages/fsharp" */ 'highlight.js/lib/languages/fsharp'),
    gams: () => import(/* webpackChunkName: "highlight.js/languages/gams" */ 'highlight.js/lib/languages/gams'),
    gauss: () => import(/* webpackChunkName: "highlight.js/languages/gauss" */ 'highlight.js/lib/languages/gauss'),
    gcode: () => import(/* webpackChunkName: "highlight.js/languages/gcode" */ 'highlight.js/lib/languages/gcode'),
    gherkin: () =>
      import(/* webpackChunkName: "highlight.js/languages/gherkin" */ 'highlight.js/lib/languages/gherkin'),
    glsl: () => import(/* webpackChunkName: "highlight.js/languages/glsl" */ 'highlight.js/lib/languages/glsl'),
    go: () => import(/* webpackChunkName: "highlight.js/languages/go" */ 'highlight.js/lib/languages/go'),
    gradle: () => import(/* webpackChunkName: "highlight.js/languages/gradle" */ 'highlight.js/lib/languages/gradle'),
    graphql: () =>
      import(/* webpackChunkName: "highlight.js/languages/graphql" */ 'highlight.js/lib/languages/graphql'),
    haskell: () =>
      import(/* webpackChunkName: "highlight.js/languages/haskell" */ 'highlight.js/lib/languages/haskell'),
    hsp: () => import(/* webpackChunkName: "highlight.js/languages/hsp" */ 'highlight.js/lib/languages/hsp'),
    http: () => import(/* webpackChunkName: "highlight.js/languages/http" */ 'highlight.js/lib/languages/http'),
    hy: () => import(/* webpackChunkName: "highlight.js/languages/hy" */ 'highlight.js/lib/languages/hy'),
    ini: () => import(/* webpackChunkName: "highlight.js/languages/ini" */ 'highlight.js/lib/languages/ini'),
    inform7: () =>
      import(/* webpackChunkName: "highlight.js/languages/inform7" */ 'highlight.js/lib/languages/inform7'),
    irpf90: () => import(/* webpackChunkName: "highlight.js/languages/irpf90" */ 'highlight.js/lib/languages/irpf90'),
    isbl: () => import(/* webpackChunkName: "highlight.js/languages/isbl" */ 'highlight.js/lib/languages/isbl'),
    java: () => import(/* webpackChunkName: "highlight.js/languages/java" */ 'highlight.js/lib/languages/java'),
    javascript: () =>
      import(/* webpackChunkName: "highlight.js/languages/javascript" */ 'highlight.js/lib/languages/javascript'),
    'jboss-cli': () =>
      import(/* webpackChunkName: "highlight.js/languages/jboss-cli" */ 'highlight.js/lib/languages/jboss-cli'),
    json: () => import(/* webpackChunkName: "highlight.js/languages/json" */ 'highlight.js/lib/languages/json'),
    julia: () => import(/* webpackChunkName: "highlight.js/languages/julia" */ 'highlight.js/lib/languages/julia'),
    'julia-repl': () =>
      import(/* webpackChunkName: "highlight.js/languages/julia-repl" */ 'highlight.js/lib/languages/julia-repl'),
    kotlin: () => import(/* webpackChunkName: "highlight.js/languages/kotlin" */ 'highlight.js/lib/languages/kotlin'),
    lasso: () => import(/* webpackChunkName: "highlight.js/languages/lasso" */ 'highlight.js/lib/languages/lasso'),
    latex: () => import(/* webpackChunkName: "highlight.js/languages/latex" */ 'highlight.js/lib/languages/latex'),
    ldif: () => import(/* webpackChunkName: "highlight.js/languages/ldif" */ 'highlight.js/lib/languages/ldif'),
    less: () => import(/* webpackChunkName: "highlight.js/languages/less" */ 'highlight.js/lib/languages/less'),
    lisp: () => import(/* webpackChunkName: "highlight.js/languages/lisp" */ 'highlight.js/lib/languages/lisp'),
    livescript: () =>
      import(/* webpackChunkName: "highlight.js/languages/livescript" */ 'highlight.js/lib/languages/livescript'),
    llvm: () => import(/* webpackChunkName: "highlight.js/languages/llvm" */ 'highlight.js/lib/languages/llvm'),
    lua: () => import(/* webpackChunkName: "highlight.js/languages/lua" */ 'highlight.js/lib/languages/lua'),
    makefile: () =>
      import(/* webpackChunkName: "highlight.js/languages/makefile" */ 'highlight.js/lib/languages/makefile'),
    markdown: () =>
      import(/* webpackChunkName: "highlight.js/languages/markdown" */ 'highlight.js/lib/languages/markdown'),
    mathematica: () =>
      import(/* webpackChunkName: "highlight.js/languages/mathematica" */ 'highlight.js/lib/languages/mathematica'),
    matlab: () => import(/* webpackChunkName: "highlight.js/languages/matlab" */ 'highlight.js/lib/languages/matlab'),
    maxima: () => import(/* webpackChunkName: "highlight.js/languages/maxima" */ 'highlight.js/lib/languages/maxima'),
    mel: () => import(/* webpackChunkName: "highlight.js/languages/mel" */ 'highlight.js/lib/languages/mel'),
    mercury: () =>
      import(/* webpackChunkName: "highlight.js/languages/mercury" */ 'highlight.js/lib/languages/mercury'),
    mipsasm: () =>
      import(/* webpackChunkName: "highlight.js/languages/mipsasm" */ 'highlight.js/lib/languages/mipsasm'),
    mizar: () => import(/* webpackChunkName: "highlight.js/languages/mizar" */ 'highlight.js/lib/languages/mizar'),
    monkey: () => import(/* webpackChunkName: "highlight.js/languages/monkey" */ 'highlight.js/lib/languages/monkey'),
    moonscript: () =>
      import(/* webpackChunkName: "highlight.js/languages/moonscript" */ 'highlight.js/lib/languages/moonscript'),
    nestedtext: () =>
      import(/* webpackChunkName: "highlight.js/languages/nestedtext" */ 'highlight.js/lib/languages/nestedtext'),
    nginx: () => import(/* webpackChunkName: "highlight.js/languages/nginx" */ 'highlight.js/lib/languages/nginx'),
    'node-repl': () =>
      import(/* webpackChunkName: "highlight.js/languages/node-repl" */ 'highlight.js/lib/languages/node-repl'),
    nsis: () => import(/* webpackChunkName: "highlight.js/languages/nsis" */ 'highlight.js/lib/languages/nsis'),
    objectivec: () =>
      import(/* webpackChunkName: "highlight.js/languages/objectivec" */ 'highlight.js/lib/languages/objectivec'),
    ocaml: () => import(/* webpackChunkName: "highlight.js/languages/ocaml" */ 'highlight.js/lib/languages/ocaml'),
    openscad: () =>
      import(/* webpackChunkName: "highlight.js/languages/openscad" */ 'highlight.js/lib/languages/openscad'),
    oxygene: () =>
      import(/* webpackChunkName: "highlight.js/languages/oxygene" */ 'highlight.js/lib/languages/oxygene'),
    perl: () => import(/* webpackChunkName: "highlight.js/languages/perl" */ 'highlight.js/lib/languages/perl'),
    pgsql: () => import(/* webpackChunkName: "highlight.js/languages/pgsql" */ 'highlight.js/lib/languages/pgsql'),
    php: () => import(/* webpackChunkName: "highlight.js/languages/php" */ 'highlight.js/lib/languages/php'),
    'php-template': () =>
      import(/* webpackChunkName: "highlight.js/languages/php-template" */ 'highlight.js/lib/languages/php-template'),
    plaintext: () =>
      import(/* webpackChunkName: "highlight.js/languages/plaintext" */ 'highlight.js/lib/languages/plaintext'),
    powershell: () =>
      import(/* webpackChunkName: "highlight.js/languages/powershell" */ 'highlight.js/lib/languages/powershell'),
    processing: () =>
      import(/* webpackChunkName: "highlight.js/languages/processing" */ 'highlight.js/lib/languages/processing'),
    profile: () =>
      import(/* webpackChunkName: "highlight.js/languages/profile" */ 'highlight.js/lib/languages/profile'),
    prolog: () => import(/* webpackChunkName: "highlight.js/languages/prolog" */ 'highlight.js/lib/languages/prolog'),
    properties: () =>
      import(/* webpackChunkName: "highlight.js/languages/properties" */ 'highlight.js/lib/languages/properties'),
    protobuf: () =>
      import(/* webpackChunkName: "highlight.js/languages/protobuf" */ 'highlight.js/lib/languages/protobuf'),
    puppet: () => import(/* webpackChunkName: "highlight.js/languages/puppet" */ 'highlight.js/lib/languages/puppet'),
    python: () => import(/* webpackChunkName: "highlight.js/languages/python" */ 'highlight.js/lib/languages/python'),
    'python-repl': () =>
      import(/* webpackChunkName: "highlight.js/languages/python-repl" */ 'highlight.js/lib/languages/python-repl'),
    q: () => import(/* webpackChunkName: "highlight.js/languages/q" */ 'highlight.js/lib/languages/q'),
    qml: () => import(/* webpackChunkName: "highlight.js/languages/qml" */ 'highlight.js/lib/languages/qml'),
    reasonml: () =>
      import(/* webpackChunkName: "highlight.js/languages/reasonml" */ 'highlight.js/lib/languages/reasonml'),
    rib: () => import(/* webpackChunkName: "highlight.js/languages/rib" */ 'highlight.js/lib/languages/rib'),
    roboconf: () =>
      import(/* webpackChunkName: "highlight.js/languages/roboconf" */ 'highlight.js/lib/languages/roboconf'),
    routeros: () =>
      import(/* webpackChunkName: "highlight.js/languages/routeros" */ 'highlight.js/lib/languages/routeros'),
    ruleslanguage: () =>
      import(/* webpackChunkName: "highlight.js/languages/ruleslanguage" */ 'highlight.js/lib/languages/ruleslanguage'),
    ruby: () => import(/* webpackChunkName: "highlight.js/languages/ruby" */ 'highlight.js/lib/languages/ruby'),
    rust: () => import(/* webpackChunkName: "highlight.js/languages/rust" */ 'highlight.js/lib/languages/rust'),
    sas: () => import(/* webpackChunkName: "highlight.js/languages/sas" */ 'highlight.js/lib/languages/sas'),
    scala: () => import(/* webpackChunkName: "highlight.js/languages/scala" */ 'highlight.js/lib/languages/scala'),
    scilab: () => import(/* webpackChunkName: "highlight.js/languages/scilab" */ 'highlight.js/lib/languages/scilab'),
    scss: () => import(/* webpackChunkName: "highlight.js/languages/scss" */ 'highlight.js/lib/languages/scss'),
    scheme: () => import(/* webpackChunkName: "highlight.js/languages/scheme" */ 'highlight.js/lib/languages/scheme'),
    shell: () => import(/* webpackChunkName: "highlight.js/languages/shell" */ 'highlight.js/lib/languages/shell'),
    smali: () => import(/* webpackChunkName: "highlight.js/languages/smali" */ 'highlight.js/lib/languages/smali'),
    sml: () => import(/* webpackChunkName: "highlight.js/languages/sml" */ 'highlight.js/lib/languages/sml'),
    sql: () => import(/* webpackChunkName: "highlight.js/languages/sql" */ 'highlight.js/lib/languages/sql'),
    sqf: () => import(/* webpackChunkName: "highlight.js/languages/sqf" */ 'highlight.js/lib/languages/sqf'),
    stan: () => import(/* webpackChunkName: "highlight.js/languages/stan" */ 'highlight.js/lib/languages/stan'),
    stata: () => import(/* webpackChunkName: "highlight.js/languages/stata" */ 'highlight.js/lib/languages/stata'),
    step21: () => import(/* webpackChunkName: "highlight.js/languages/step21" */ 'highlight.js/lib/languages/step21'),
    stylus: () => import(/* webpackChunkName: "highlight.js/languages/stylus" */ 'highlight.js/lib/languages/stylus'),
    subunit: () =>
      import(/* webpackChunkName: "highlight.js/languages/subunit" */ 'highlight.js/lib/languages/subunit'),
    swift: () => import(/* webpackChunkName: "highlight.js/languages/swift" */ 'highlight.js/lib/languages/swift'),
    taggerscript: () =>
      import(/* webpackChunkName: "highlight.js/languages/taggerscript" */ 'highlight.js/lib/languages/taggerscript'),
    tap: () => import(/* webpackChunkName: "highlight.js/languages/tap" */ 'highlight.js/lib/languages/tap'),
    tcl: () => import(/* webpackChunkName: "highlight.js/languages/tcl" */ 'highlight.js/lib/languages/tcl'),
    thrift: () => import(/* webpackChunkName: "highlight.js/languages/thrift" */ 'highlight.js/lib/languages/thrift'),
    tp: () => import(/* webpackChunkName: "highlight.js/languages/tp" */ 'highlight.js/lib/languages/tp'),
    typescript: () =>
      import(/* webpackChunkName: "highlight.js/languages/typescript" */ 'highlight.js/lib/languages/typescript'),
    vbnet: () => import(/* webpackChunkName: "highlight.js/languages/vbnet" */ 'highlight.js/lib/languages/vbnet'),
    vbscript: () =>
      import(/* webpackChunkName: "highlight.js/languages/vbscript" */ 'highlight.js/lib/languages/vbscript'),
    'vbscript-html': () =>
      import(/* webpackChunkName: "highlight.js/languages/vbscript-html" */ 'highlight.js/lib/languages/vbscript-html'),
    verilog: () =>
      import(/* webpackChunkName: "highlight.js/languages/verilog" */ 'highlight.js/lib/languages/verilog'),
    vhdl: () => import(/* webpackChunkName: "highlight.js/languages/vhdl" */ 'highlight.js/lib/languages/vhdl'),
    vim: () => import(/* webpackChunkName: "highlight.js/languages/vim" */ 'highlight.js/lib/languages/vim'),
    wasm: () => import(/* webpackChunkName: "highlight.js/languages/wasm" */ 'highlight.js/lib/languages/wasm'),
    wren: () => import(/* webpackChunkName: "highlight.js/languages/wren" */ 'highlight.js/lib/languages/wren'),
    x86asm: () => import(/* webpackChunkName: "highlight.js/languages/x86asm" */ 'highlight.js/lib/languages/x86asm'),
    xquery: () => import(/* webpackChunkName: "highlight.js/languages/xquery" */ 'highlight.js/lib/languages/xquery'),
    xl: () => import(/* webpackChunkName: "highlight.js/languages/xl" */ 'highlight.js/lib/languages/xl'),
    xml: () => import(/* webpackChunkName: "highlight.js/languages/xml" */ 'highlight.js/lib/languages/xml'),
    yaml: () => import(/* webpackChunkName: "highlight.js/languages/yaml" */ 'highlight.js/lib/languages/yaml'),
  };
  // 含めるリストを適用してフィルタリング
  const languageModules: Record<string, LanguageModule> = Object.keys(availableLanguageModules)
    .filter((key) => includeLanguages.includes(key))
    .reduce(
      (obj, key) => {
        obj[key] = availableLanguageModules[key];
        return obj;
      },
      {} as Record<string, LanguageModule>
    );

  for (const [langName, importFn] of Object.entries(languageModules)) {
    const module = await importFn(); // eslint-disable-line no-await-in-loop
    hljs.registerLanguage(langName, module.default);
  }
};

const loadThemeCss = (theme: string, rootPath: string): void => {
  if (document.querySelector('#highlight-theme') === null) {
    const styleTag = document.createElement('link');
    styleTag.id = 'highlight-theme';
    styleTag.rel = 'stylesheet';
    styleTag.href = `${rootPath}js/dest/highlight.js/styles/${theme}.min.css`; // 動的に生成した CSS のパス
    document.head.appendChild(styleTag);
  }
};

const highlight = async (
  context: HTMLElement,
  mark: string,
  languages: string[],
  theme: string,
  rootPath: string
): Promise<void> => {
  const preElements = context.querySelectorAll<HTMLElement>(mark);
  if (preElements.length === 0) {
    return;
  }
  loadThemeCss(theme, rootPath);
  await loadLanguageModule(hljs, languages || ['html']);

  hljs.configure({
    ignoreUnescapedHTML: true,
  });

  // ハイライトを実行
  preElements.forEach((pre) => {
    // <pre> 内に <code> 要素が含まれているか確認してなければ <code> 要素を追加
    const codeElement = pre.querySelector('code');
    if (!codeElement) {
      const content = pre.innerHTML;
      const newCodeElement = document.createElement('code');
      newCodeElement.innerHTML = content;
      pre.innerHTML = '';
      pre.appendChild(newCodeElement);
    }
    pre.classList.add('code-highlight');
    hljs.highlightElement(pre);
  });
};

export default highlight;
