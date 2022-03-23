# Qooqle

This is a simple not Google search engine with an attempt of a very simple crawling mechanism <br>

## How it works
The app gets search data from db and the db gets data by crawl.php <br>
You run crawl.php to a specific website and it will go through that website and recursively goes through links on that website and gets the following:
- url
- title
- description
- keyword

After getting the above it will save into the database which will then be read by Qooqle
