<?php
namespace SEOJusAI\Tasks;
use SEOJusAI\AI\PromptLibrary;
use SEOJusAI\AI\LLMAdapterFactory;
use SEOJusAI\Repository\SeoMetaRepository;
final class GenerateSeoMetaTask {
  public function __invoke(array $payload): array {
    $post=get_post((int)$payload['post_id']);
    if(!$post) SeoMetaRepository::save($payload['decision_hash']??'',(int)$payload['post_id'],$meta);
        return [];
    $text=$post->post_title."\n".wp_strip_all_tags($post->post_content);
    $llm=LLMAdapterFactory::make();
    SeoMetaRepository::save($payload['decision_hash']??'',(int)$payload['post_id'],$meta);
        return [
      'seo_title'=>trim(str_replace('SEO Title:','',$llm->complete(PromptLibrary::seoTitle($text))['raw'])),
      'meta_description'=>trim(str_replace('Meta description:','',$llm->complete(PromptLibrary::metaDescription($text))['raw']))
    ];
  }
}
