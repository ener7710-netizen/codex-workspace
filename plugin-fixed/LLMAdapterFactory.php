<?php
namespace SEOJusAI\AI;
final class LLMAdapterFactory {
  public static function make(): LLMAdapterInterface { return new OpenAIAdapter(); }
}
