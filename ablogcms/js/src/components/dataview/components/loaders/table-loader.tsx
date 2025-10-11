import React from 'react';
import ContentLoader from 'react-content-loader';

interface TableLoaderProps extends React.CustomComponentPropsWithRef<typeof ContentLoader> {
  /** 行数 */
  rows?: number;
  /** 列数（チェックボックス列を含む） */
  columns?: number;
  /** 行間のスペース */
  rowGap?: number;
  /** 列間のスペース */
  columnGap?: number;
  /** 上下左右の余白 */
  padding?: number;
  /** 1行あたりの実際の高さ（px） */
  rowHeight?: number;
}

// 通常列の基本幅（これを基準に変化をつける）
const BASE_COLUMN_WIDTH = 90;

// viewBox内での要素の高さ
const ELEMENT_HEIGHT = 12;

// 列のwidthを取得する関数（インデックスに応じて幅を変える）
function getColumnWidth(columnIndex: number): number {
  if (columnIndex === 0) {
    return ELEMENT_HEIGHT;
  }

  // インデックスに応じて異なる幅を返す
  switch ((columnIndex - 1) % 3) {
    case 0:
      return BASE_COLUMN_WIDTH;
    case 1:
      return BASE_COLUMN_WIDTH * 2;
    case 2:
      return BASE_COLUMN_WIDTH * 0.8;
    default:
      return BASE_COLUMN_WIDTH;
  }
}

const TableLoader = ({
  rows = 10,
  columns = 12,
  rowGap = 16,
  columnGap = 16,
  padding = 0,
  rowHeight = 32,
  ...props
}: TableLoaderProps) => {
  // 全体の幅を計算
  const totalWidth =
    Array.from({ length: columns }).reduce<number>((sum, _, index) => sum + getColumnWidth(index), 0) +
    (columns - 1) * columnGap +
    padding * 2;

  // viewBox用の高さを計算
  const totalHeight = rows * ELEMENT_HEIGHT + (rows - 1) * rowGap + padding * 2;

  // 実際の表示高さを計算（行数 × 1行の高さ + 行間）
  const displayHeight = rows * rowHeight + (rows - 1) * (rowGap / 2);

  // 列のx座標を計算
  const getColumnX = (columnIndex: number): number => {
    return (
      padding +
      Array.from({ length: columnIndex }).reduce<number>((sum, _, index) => sum + getColumnWidth(index) + columnGap, 0)
    );
  };

  // 行のy座標を計算
  const getRowY = (rowIndex: number): number => {
    return padding + rowIndex * (ELEMENT_HEIGHT + rowGap);
  };

  return (
    <ContentLoader
      width="100%"
      height={displayHeight}
      viewBox={`0 0 ${totalWidth} ${totalHeight}`}
      backgroundColor="#f3f3f3"
      foregroundColor="#ecebeb"
      preserveAspectRatio="xMinYMin slice"
      {...props}
    >
      {Array.from({ length: rows }).map((_, rowIndex) =>
        // eslint-disable-next-line react/no-array-index-key
        Array.from({ length: columns }).map((_, columnIndex) => (
          <rect
            // eslint-disable-next-line react/no-array-index-key
            key={`${rowIndex}-${columnIndex}`}
            x={getColumnX(columnIndex)}
            y={getRowY(rowIndex)}
            rx="0"
            ry="0"
            width={getColumnWidth(columnIndex)}
            height={ELEMENT_HEIGHT}
          />
        ))
      )}
    </ContentLoader>
  );
};

export default TableLoader;
