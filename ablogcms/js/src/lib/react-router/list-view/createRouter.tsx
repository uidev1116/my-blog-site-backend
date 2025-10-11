import { type RouteObject, ScrollRestoration, createBrowserRouter, useParams, useRouteError } from 'react-router';
import { type ReactNode, useMemo } from 'react';
import { AcmsContextProvider } from '../../../stores/acms';

/**
 * エラーコンポーネントを作成する
 */
const ErrorElement = ({ element }: { element: ReactNode }): ReactNode => {
  const error = useRouteError();
  console.error(error); // eslint-disable-line no-console
  return <>{element}</>; // eslint-disable-line react/jsx-no-useless-fragment
};

/**
 * ルートコンポーネントを作成する
 */
const RouteElement = ({ element }: { element: ReactNode }): RouteObject['element'] => {
  const params = useParams();
  const context = useMemo(() => ACMS.Library.parseAcmsPath(decodeURI(params['*'] as string)), [params]);

  return (
    <>
      <AcmsContextProvider context={context}>{element}</AcmsContextProvider>
      <ScrollRestoration />
    </>
  );
};

/**
 * 管理画面のルートコンポーネントを作成する
 *
 * @warning
 * このメソッドはReactコンポーネントの中で利用すると、コンポーネントが表示されない不具合があります。
 * コンポーネントの外で呼び出すようにしてください。
 */
export default function createRouter({ element, errorElement, ...rest }: RouteObject) {
  // ルーターの作成
  const router = createBrowserRouter([
    {
      path: '*',
      element: <RouteElement element={element} />,
      errorElement: <ErrorElement element={errorElement} />,
      ...rest,
    },
  ]);

  return router;
}
