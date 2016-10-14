class SearchPage < ArticlePage
  include PageObject

  div(:list_of_results, css: '.card-list')
end
