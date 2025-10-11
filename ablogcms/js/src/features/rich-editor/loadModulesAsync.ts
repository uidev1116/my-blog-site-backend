export const loadRichEditorModulesAsync = async () => {
  const { default: modules } = await import(/* webpackChunkName: "rich-editor-modules" */ './loadModules');

  return modules;
};

export default loadRichEditorModulesAsync;
