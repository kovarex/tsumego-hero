besogo.addTest = function(suite, name, method)
{
  if (besogo.tests == null)
    besogo.tests = [];
  var test = [];
  test.name = name;
  test.suite = suite;
  test.method = method;
  besogo.tests.push(test);
};

CHECK = function(A)
{
  if (A)
    return;
  let error = [];
  error.message = "Check failed";
  error.stack = console.trace();
  throw error;
}

CHECK_EQUALS = function(A, B)
{
  if (A == B)
    return;
  let error = [];
  error.message = "Expected " + B + " but was " + A;
  error.stack = console.trace();
  throw error;
}
