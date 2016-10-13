# 2.0

The 2.0 release introduces two new methods as part of the ServiceObserverInterface: `alterRequest` and `alterResponse`. These may change request parameters and response contents. 
In previous versions this was typically implemented in `notifyBefore` and `notifyAfter`. 
 
To make sure that the logic is implemented in the correct methods, it is now forbidden to alter the request in the `notifyBefore` and alter the response in the `notifyAfter`. This way, the cache observer can be sure that it may write the cache within the `notifyAfter` and compose the cache key within the `notifyBefore`.

Usually it is safe to say that all `notifyBefore` can be replaced by `alterRequest` and all `notifyAfter` can be replaced by `alterResponse`, though _logic dictates_ that the alter-methods should only be used for actually altering the request or response respectively.
