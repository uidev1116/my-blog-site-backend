import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';
import CategorySelect from './category-select';
import useCategoryOptionsSWR from '../../hooks/use-category-options-swr';
import type { CategoryOption, CreatedCategoryDTO } from '../../types';

vi.mock('../../hooks/use-category-options-swr');

vi.mock('../category-create-modal/category-create-modal', () => ({
  default: ({
    isOpen,
    onClose,
    onCreate,
  }: {
    isOpen: boolean;
    onClose: () => void;
    onCreate: (category: CreatedCategoryDTO) => void;
  }) =>
    isOpen ? (
      <div data-testid="category-create-modal">
        <button type="button" onClick={() => onCreate({ id: 99, name: '新規作成カテゴリー' })}>
          作成
        </button>
        <button type="button" onClick={onClose}>
          閉じる
        </button>
      </div>
    ) : null,
}));

type CategoryOptionsSWRReturn = ReturnType<typeof useCategoryOptionsSWR>;

const mockSWR = (overrides: Partial<CategoryOptionsSWRReturn> = {}): void => {
  vi.mocked(useCategoryOptionsSWR).mockReturnValue({
    options: undefined,
    isLoading: false,
    error: undefined,
    ...overrides,
  } as CategoryOptionsSWRReturn);
};

describe('CategorySelect', () => {
  afterEach(() => {
    vi.resetAllMocks();
  });

  describe('defaultValue による初期値の設定', () => {
    // 固定オプション(noOption/mtOption)の有無で初期化ロジックが分岐しないことを担保するためテーブル化
    it.each([
      { name: 'noOption=true', props: { noOption: true } },
      { name: 'mtOption=true', props: { mtOption: true } },
      { name: '固定オプションなし', props: {} },
    ])(
      '$name で defaultValue を指定したとき、API レスポンス到着後に選択中のカテゴリーが表示される',
      async ({ props }) => {
        mockSWR({ options: undefined, isLoading: true });

        const { rerender } = render(<CategorySelect {...props} defaultValue="5" />);

        expect(screen.queryByText('カテゴリー5')).not.toBeInTheDocument();

        mockSWR({
          options: [{ value: '5', label: 'カテゴリー5' }],
          isLoading: false,
        });

        rerender(<CategorySelect {...props} defaultValue="5" />);

        await waitFor(() => {
          expect(screen.getByText('カテゴリー5')).toBeInTheDocument();
        });
      }
    );

    it('isLoading=true が継続する間は defaultValue があっても初期化されない', () => {
      mockSWR({ options: undefined, isLoading: true });

      render(<CategorySelect noOption defaultValue="5" />);

      // 固定オプションだけが options に積まれていても、isLoading の間は初期化されない
      expect(screen.queryByText('カテゴリー5')).not.toBeInTheDocument();
      expect(screen.getByText('category.select_placeholder')).toBeInTheDocument();
    });

    it('defaultValue を CategoryOption オブジェクト形式で渡したとき、value から抽出して初期化される', async () => {
      mockSWR({
        options: [{ value: '12', label: 'カテゴリー12' }],
        isLoading: false,
      });

      const defaultValue: CategoryOption = { value: '12', label: 'カテゴリー12（古いラベル）' };
      render(<CategorySelect defaultValue={defaultValue} />);

      // ラベルは API レスポンス側のものが表示される（value で引き当てた結果）
      await waitFor(() => {
        expect(screen.getByText('カテゴリー12')).toBeInTheDocument();
      });
      expect(screen.queryByText('カテゴリー12（古いラベル）')).not.toBeInTheDocument();
    });

    it.each([
      { name: 'defaultValue が API レスポンスに存在しない', defaultValue: '999' },
      { name: 'defaultValue を指定しない', defaultValue: undefined },
    ])('$name 場合、初期化後は何も選択されない', async ({ defaultValue }) => {
      mockSWR({
        options: [{ value: '10', label: 'カテゴリー10' }],
        isLoading: false,
      });

      render(<CategorySelect defaultValue={defaultValue} />);

      await waitFor(() => {
        expect(screen.getByText('category.select_placeholder')).toBeInTheDocument();
      });
    });

    it('一度初期化された後に defaultValue prop が変わっても、選択値は上書きされない', async () => {
      mockSWR({
        options: [
          { value: '1', label: 'カテゴリー1' },
          { value: '2', label: 'カテゴリー2' },
        ],
        isLoading: false,
      });

      const { rerender } = render(<CategorySelect defaultValue="1" />);

      await waitFor(() => {
        expect(screen.getByText('カテゴリー1')).toBeInTheDocument();
      });

      rerender(<CategorySelect defaultValue="2" />);

      expect(screen.getByText('カテゴリー1')).toBeInTheDocument();
      expect(screen.queryByText('カテゴリー2')).not.toBeInTheDocument();
    });
  });

  describe('固定オプション', () => {
    it.each([
      { props: { noOption: true }, expectedLabel: 'category.select_no_option_label' },
      { props: { mtOption: true }, expectedLabel: 'category.select_mt_option_label' },
    ])('$props で固定ラベル($expectedLabel)がメニューに追加される', ({ props, expectedLabel }) => {
      mockSWR({
        options: [{ value: '1', label: 'カテゴリー1' }],
        isLoading: false,
      });

      render(<CategorySelect {...props} />);

      fireEvent.mouseDown(screen.getByRole('combobox'));

      expect(screen.getByText(expectedLabel)).toBeInTheDocument();
    });
  });

  describe('SWR フックへのパラメータ伝搬', () => {
    it('narrowDown prop の値が useCategoryOptionsSWR の params にそのまま渡る', () => {
      mockSWR({ options: [], isLoading: false });

      render(<CategorySelect narrowDown />);

      expect(useCategoryOptionsSWR).toHaveBeenCalledWith(expect.objectContaining({ narrowDown: true }));
    });

    it('narrowDown を指定しないときは既定値 false が渡る', () => {
      mockSWR({ options: [], isLoading: false });

      render(<CategorySelect />);

      expect(useCategoryOptionsSWR).toHaveBeenCalledWith(expect.objectContaining({ narrowDown: false }));
    });

    it('defaultValue で指定した cid が currentCid として SWR フックに伝搬する', () => {
      mockSWR({ options: [], isLoading: false });

      render(<CategorySelect defaultValue="42" />);

      expect(useCategoryOptionsSWR).toHaveBeenCalledWith(expect.objectContaining({ currentCid: 42 }));
    });
  });

  describe('選択操作', () => {
    it('オプションを選択すると onChange がそのオプションで呼ばれ、画面表示も切り替わる', async () => {
      mockSWR({
        options: [
          { value: '1', label: 'カテゴリー1' },
          { value: '2', label: 'カテゴリー2' },
        ],
        isLoading: false,
      });

      const handleChange = vi.fn();
      render(<CategorySelect onChange={handleChange} />);

      fireEvent.mouseDown(screen.getByRole('combobox'));
      fireEvent.click(screen.getByText('カテゴリー2'));

      await waitFor(() => {
        expect(handleChange).toHaveBeenCalledWith({ value: '2', label: 'カテゴリー2' });
      });
      expect(screen.getByText('カテゴリー2')).toBeInTheDocument();
    });

    it('Backspace で選択を解除すると onChange が null で呼ばれる', async () => {
      mockSWR({
        options: [{ value: '1', label: 'カテゴリー1' }],
        isLoading: false,
      });

      const handleChange = vi.fn();
      render(<CategorySelect defaultValue="1" isClearable onChange={handleChange} />);

      await waitFor(() => {
        expect(screen.getByText('カテゴリー1')).toBeInTheDocument();
      });

      // react-select の backspaceRemovesValue 経路で value をクリア
      const input = screen.getByRole('combobox');
      fireEvent.focus(input);
      fireEvent.keyDown(input, { key: 'Backspace', code: 'Backspace' });

      await waitFor(() => {
        expect(handleChange).toHaveBeenCalledWith(null);
      });
    });
  });

  describe('カテゴリー作成 (isCreatable)', () => {
    it.each([
      { isCreatable: true, shouldShow: true },
      { isCreatable: false, shouldShow: false },
    ])('isCreatable=$isCreatable のとき、追加ボタンの表示は $shouldShow になる', ({ isCreatable, shouldShow }) => {
      mockSWR({ options: [], isLoading: false });

      render(<CategorySelect isCreatable={isCreatable} />);

      if (shouldShow) {
        expect(screen.getByText('category.add')).toBeInTheDocument();
      } else {
        expect(screen.queryByText('category.add')).not.toBeInTheDocument();
      }
    });

    it('追加ボタンを押すとモーダルが開き、閉じるボタンで閉じる', () => {
      mockSWR({ options: [], isLoading: false });

      render(<CategorySelect isCreatable />);

      expect(screen.queryByTestId('category-create-modal')).not.toBeInTheDocument();

      fireEvent.click(screen.getByText('category.add'));
      expect(screen.getByTestId('category-create-modal')).toBeInTheDocument();

      fireEvent.click(screen.getByText('閉じる'));
      expect(screen.queryByTestId('category-create-modal')).not.toBeInTheDocument();
    });

    it('モーダルでカテゴリーを作成すると、その値がセレクトの選択値として反映されモーダルは閉じる', async () => {
      mockSWR({ options: [], isLoading: false });

      render(<CategorySelect isCreatable />);

      fireEvent.click(screen.getByText('category.add'));
      fireEvent.click(screen.getByText('作成'));

      await waitFor(() => {
        expect(screen.getByText('新規作成カテゴリー')).toBeInTheDocument();
      });
      expect(screen.queryByTestId('category-create-modal')).not.toBeInTheDocument();
    });
  });

  describe('検索キーワード入力', () => {
    beforeEach(() => {
      vi.useFakeTimers({ shouldAdvanceTime: true });
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('入力直後のデバウンス猶予内ではフックの keyword は更新されない', async () => {
      mockSWR({
        options: [{ value: '1', label: 'カテゴリー1' }],
        isLoading: false,
      });

      render(<CategorySelect />);
      vi.mocked(useCategoryOptionsSWR).mockClear();

      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'カテゴリー' } });

      await act(async () => {
        await vi.advanceTimersByTimeAsync(100);
      });

      expect(vi.mocked(useCategoryOptionsSWR).mock.calls.some(([params]) => params?.keyword === 'カテゴリー')).toBe(
        false
      );
    });

    it('デバウンス猶予が経過するとフックが新しい keyword で呼び出される', async () => {
      mockSWR({
        options: [{ value: '1', label: 'カテゴリー1' }],
        isLoading: false,
      });

      render(<CategorySelect />);

      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'カテゴリー' } });

      await act(async () => {
        await vi.advanceTimersByTimeAsync(900);
      });

      expect(vi.mocked(useCategoryOptionsSWR).mock.calls.some(([params]) => params?.keyword === 'カテゴリー')).toBe(
        true
      );
    });

    it('検索結果が 0 件のとき "該当なし" メッセージが表示される', async () => {
      mockSWR({
        options: [],
        isLoading: false,
      });

      render(<CategorySelect />);

      fireEvent.mouseDown(screen.getByRole('combobox'));

      await waitFor(() => {
        expect(screen.getByText('category.select_notfound')).toBeInTheDocument();
      });
    });
  });
});
