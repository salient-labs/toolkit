<?php declare(strict_types=1);

namespace Salient\ApiGen;

use ApiGen\Analyzer\Filter;
use ApiGen\Info\ClassInfo;
use ApiGen\Info\ClassLikeInfo;
use ApiGen\Info\MemberInfo;
use ApiGen\Info\MethodInfo;
use ApiGen\Info\TraitInfo;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use Salient\Utility\Str;

class AnalyzerFilter extends Filter
{
    public function filterClassLikeNode(ClassLike $node): bool
    {
        return $node->namespacedName !== null
            && Str::startsWith($node->namespacedName->toString(), 'Salient\\')
            && $this->hasTag($node, 'api');
    }

    public function filterMemberInfo(ClassLikeInfo $classLike, MemberInfo $member): bool
    {
        $isConstructor = $member instanceof MethodInfo
            && $member->nameLower === '__construct';

        if (
            $member->private
            && !$classLike instanceof TraitInfo
            && !$isConstructor
        ) {
            return false;
        }

        if (
            $member->protected
            && $classLike instanceof ClassInfo
            && $classLike->final
            && !$isConstructor
        ) {
            return false;
        }

        if (
            $isConstructor
            && $classLike instanceof ClassInfo
            && !isset($member->tags['api'])
        ) {
            return false;
        }

        return true;
    }

    private function hasTag(Node $node, string $tag): bool
    {
        $tags = $this->getTags($node);

        return isset($tags[$tag]);
    }

    /**
     * @return array<string,PhpDocTagValueNode[]>
     */
    private function getTags(Node $node): array
    {
        /** @var PhpDocNode */
        $phpDoc = $node->getAttribute('phpDoc') ?? new PhpDocNode([]);

        foreach ($phpDoc->getTags() as $tag) {
            if (!$tag->value instanceof InvalidTagValueNode) {
                $tags[substr($tag->name, 1)][] = $tag->value;
            }
        }

        return $tags ?? [];
    }
}
