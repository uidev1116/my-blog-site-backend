import { loadClosureFactory, loadClosureFactoryCss, assignLoadClosure } from '../lib/loader';

type LoadClosureCollection = {
  Dispatch: any; // eslint-disable-line @typescript-eslint/no-explicit-any
  Library: any; // eslint-disable-line @typescript-eslint/no-explicit-any
};

export default (path: string): void => {
  //------------
  // collection
  const loadClosureCollection: Partial<LoadClosureCollection> = {};

  loadClosureCollection.Dispatch = {};
  loadClosureCollection.Dispatch._revision = loadClosureFactory(`${path}dispatch/_revision.js`);
  loadClosureCollection.Dispatch.Layout = loadClosureFactory(`${path}dispatch/layout.js`);
  loadClosureCollection.Dispatch.ModuleDialog = loadClosureFactory(`${path}dispatch/moduleDialog.js`);
  loadClosureCollection.Dispatch.Postinclude = loadClosureFactory(`${path}dispatch/postinclude.js`);
  loadClosureCollection.Dispatch.Postinclude._postinclude = loadClosureFactory(
    `${path}dispatch/postinclude/_postinclude.js`
  );
  loadClosureCollection.Dispatch.Linkmatchlocation = loadClosureFactory(`${path}dispatch/linkmatchlocation.js`);
  loadClosureCollection.Dispatch.Admin = loadClosureFactory(`${path}dispatch/admin.js`);
  loadClosureCollection.Dispatch.Edit = loadClosureFactory(`${path}dispatch/edit.js`);
  loadClosureCollection.Dispatch.Edit._item = loadClosureFactory(`${path}dispatch/edit/_item.js`);
  loadClosureCollection.Dispatch.Edit._tagassist = loadClosureFactory(`${path}dispatch/edit/_tagassist.js`);
  loadClosureCollection.Dispatch.Edit._inplace = loadClosureFactory(`${path}dispatch/edit/_inplace.js`);
  loadClosureCollection.Dispatch.Dialog = loadClosureFactory(`${path}dispatch/dialog.js`);
  loadClosureFactoryCss(`${ACMS.Config.jsRoot}library/jquery/ui_1.13.2/jquery-ui.min.css`);

  ACMS.Load = loadClosureCollection;

  //--------
  // define
  assignLoadClosure('ACMS.Dispatch._revision', loadClosureCollection.Dispatch._revision);
  assignLoadClosure('ACMS.Dispatch.Layout', loadClosureCollection.Dispatch.Layout);
  assignLoadClosure('ACMS.Dispatch.ModuleDialog', loadClosureCollection.Dispatch.ModuleDialog);
  assignLoadClosure('ACMS.Dispatch.Dialog', loadClosureCollection.Dispatch.Dialog);
  assignLoadClosure('ACMS.Dispatch.Postinclude.ready', loadClosureCollection.Dispatch.Postinclude);
  assignLoadClosure('ACMS.Dispatch.Postinclude.bottom', loadClosureCollection.Dispatch.Postinclude);
  assignLoadClosure('ACMS.Dispatch.Postinclude.interval', loadClosureCollection.Dispatch.Postinclude);
  assignLoadClosure('ACMS.Dispatch.Postinclude.submit', loadClosureCollection.Dispatch.Postinclude);
  assignLoadClosure('ACMS.Dispatch.Postinclude._postinclude', loadClosureCollection.Dispatch.Postinclude._postinclude);
  assignLoadClosure('ACMS.Dispatch.Linkmatchlocation.part', loadClosureCollection.Dispatch.Linkmatchlocation);
  assignLoadClosure('ACMS.Dispatch.Linkmatchlocation.full', loadClosureCollection.Dispatch.Linkmatchlocation);
  assignLoadClosure('ACMS.Dispatch.Linkmatchlocation.blog', loadClosureCollection.Dispatch.Linkmatchlocation);
  assignLoadClosure('ACMS.Dispatch.Linkmatchlocation.category', loadClosureCollection.Dispatch.Linkmatchlocation);
  assignLoadClosure('ACMS.Dispatch.Linkmatchlocation.entry', loadClosureCollection.Dispatch.Linkmatchlocation);
  assignLoadClosure('ACMS.Dispatch.Admin', loadClosureCollection.Dispatch.Admin);
  assignLoadClosure('ACMS.Dispatch.Edit', loadClosureCollection.Dispatch.Edit);
  assignLoadClosure('ACMS.Dispatch.Edit._item', loadClosureCollection.Dispatch.Edit._item);
  assignLoadClosure('ACMS.Dispatch.Edit._tagassist', loadClosureCollection.Dispatch.Edit._tagassist);
  assignLoadClosure('ACMS.Dispatch.Edit._inplace', loadClosureCollection.Dispatch.Edit._inplace);
};
