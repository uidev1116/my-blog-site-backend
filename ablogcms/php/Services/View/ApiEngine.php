<?php

namespace Acms\Services\View;

class ApiEngine implements Contracts\ViewInterface
{
    /**
     * @var \ACMS_Corrector
     */
    protected $_Corrector = null;

    /**
     * @var array
     */
    protected $json = [];

    /**
     * @var array
     */
    protected $blockData = [];

    /**
     * @var array
     */
    protected $childData = [];

    /**
     * @var array
     */
    protected $stackData = [];

    /**
     * ブロック名 → 直近の親ブロック名 のマップ
     *
     * count($blocks) >= 2 の add() 呼び出しのたびに、祖先関係を記憶する。
     * これにより、後続の count($blocks) === 1 のショートカット呼び出し時にも
     * 自動的に親ブロックへ向けて stash され、3階層以上のネストが失われない。
     *
     * @var array<string, string>
     */
    protected $blockParent = [];

    /**
     * テンプレートの初期化
     *
     * @param string $txt
     * @param \ACMS_Corrector $Corrector
     *
     * @return self
     */
    public function init($txt, $Corrector = null)
    {
        if (is_object($Corrector) && method_exists($Corrector, 'correct')) {
            $this->_Corrector =& $Corrector;
        }
        return $this;
    }

    /**
     * テンプレートを文字列で取得する
     *
     * @return string
     */
    public function get()
    {
        return json_encode($this->blockData);
    }

    /**
     * テンプレートを組み立て文字列で取得する
     *
     * add() で積み上げた blockData と引数 $vars を後勝ちでマージしてから JSON 化する。
     * これにより add()→render() の併用時に add() のデータが失われる問題を回避し、
     * 通常エンジン (Engine::render()) と同じセマンティクス (add したデータを保持) に揃える。
     * blockData 自体は書き換えない (副作用なし)。
     *
     * @param mixed $vars
     *
     * @return string
     */
    public function render($vars)
    {
        if (is_object($vars)) {
            $vars = json_decode((string)json_encode($vars), true);
        }
        if (is_array($vars)) {
            // add() で積み上げた blockData に後勝ちでマージする
            $mergedData = $this->blockData;
            foreach ($vars as $key => $value) {
                $mergedData[$key] = $value;
            }
            $vars = $mergedData;
        }
        // スカラー/null などはこれまで通り引数そのものを JSON 化して返す
        return json_encode($vars);
    }

    /**
     * @inheritDoc
     */
    public function add($blocks = [], $vars = [])
    {
        if (!is_array($blocks)) {
            $blocks = is_string($blocks) ? [$blocks] : null;
        }
        if (!is_array($vars)) {
            $vars = [];
        }

        if ($blocks === null || $blocks === []) {
            foreach ($vars as $key => $val) {
                $this->blockData[$key] = $val;
            }
            return;
        }

        // 呼び出しで明示された祖先関係 (blocks[i] は blocks[i+1] の配下) を記憶する。
        // これにより、以後に count==1 のショートカット呼び出しがあっても、
        // 記録された親ブロックへ自動的に stash できるため 3 階層以上のネストが失われない。
        $blocksCount = count($blocks);
        for ($i = 0; $i < $blocksCount - 1; $i++) {
            $this->blockParent[$blocks[$i]] = $blocks[$i + 1];
        }

        // count==1 のショートカット呼び出しで親が記録済みなら自動的に親を付与する。
        if ($blocksCount === 1 && isset($this->blockParent[$blocks[0]])) {
            $blocks[] = $this->blockParent[$blocks[0]];
            $blocksCount = 2;
        }

        if (isset($this->stackData[$blocks[0]])) {
            // スタックしていたブロックを取り出し
            $vars = $this->mergeLevel1($vars, $this->stackData[$blocks[0]]);
            unset($this->stackData[$blocks[0]]);
        }
        if ($blocksCount > 1) {
            // ルートブロックでないので、親ブロック配下に stash する。
            // :loop 子は意味論として常に配列であるべきなので、単発 add でも最初からベクター化する。
            // (2 回目以降の add は mergeLevel1 の vector append により自然に要素が追加される)
            $childIsLoop = false !== strpos($blocks[0], ':loop');
            $childValue = $childIsLoop ? [$vars] : $vars;
            if (isset($this->stackData[$blocks[1]])) {
                $this->stackData[$blocks[1]] = $this->mergeLevel1($this->stackData[$blocks[1]], [$blocks[0] => $vars]);
            } else {
                $this->stackData[$blocks[1]] = [$blocks[0] => $childValue];
            }
        } else {
            // ルートブロックを処理
            if (isset($this->blockData[$blocks[0]])) {
                $this->blockData = $this->mergeLevel1($this->blockData, [$blocks[0] => $vars]);
            } else {
                if (false !== strpos($blocks[0], ':loop')) {
                    $this->blockData[$blocks[0]] = [];
                    $this->blockData[$blocks[0]][] = $vars;
                } else {
                    $this->blockData[$blocks[0]] = $vars;
                }
            }
        }
    }

    /**
     * @param array $arr1
     * @param array $arr2
     * @return array
     */
    private function mergeLevel1($arr1, $arr2)
    {
        foreach ($arr1 as $key => $value) {
            if (isset($arr2[$key])) {
                if (!$this->isVectorArray($arr1[$key])) {
                    $arr1[$key] = [];
                    $arr1[$key][] = $value;
                }
                $arr1[$key][] = $arr2[$key];
                unset($arr2[$key]);
            }
        }
        foreach ($arr2 as $key => $value) {
            $arr1[$key] = $value;
        }
        return $arr1;
    }

    /**
     * @param array $arr
     * @return bool
     */
    private function isVectorArray($arr)
    {
        return array_values($arr) === $arr;
    }
}
